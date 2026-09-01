<?php
/**
 * WYSIWYG (2026) — shared logic.
 *
 * THE ERA MODEL
 * -------------
 * Which image is showing is derived from the clock, so any past moment can be
 * reconstructed exactly — that is what lets the artist prove which image was
 * displayed at the instant of sale.
 *
 * Naive `floor(ts / period) % count(images)` breaks the moment the image set
 * changes: a 16th image silently rewrites what every past timestamp resolves to.
 *
 * So the schedule is versioned. Each published image set opens an ERA recording
 * its start time, rotation period, and exact ordered file list. Resolving a
 * timestamp means finding its era first, then indexing within that era. Old eras
 * are immutable, so history stays true while the set stays free to grow.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function data_path(string $name): string { return DATA_DIR . '/' . $name; }

function read_json(string $file, $default) {
    if (!is_readable($file)) return $default;
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') return $default;
    $v = json_decode($raw, true);
    return is_array($v) ? $v : $default;
}

function write_json_atomic(string $file, $value): bool {
    $tmp = $file . '.tmp' . getmypid();
    $ok = file_put_contents($tmp, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
    if ($ok === false) { @unlink($tmp); return false; }
    return rename($tmp, $file);   // atomic: readers never see a half-written file
}

/** Ordered list of image filenames currently sitting in images/. */
function scan_images(): array {
    $out = [];
    foreach ((array) @scandir(IMAGES_DIR) as $f) {
        if ($f === '.' || $f === '..') continue;
        // Skip dotfiles, and in particular macOS AppleDouble resource forks
        // (._name.jpeg), which a tar/scp from a Mac will happily deliver and
        // which would otherwise be counted as images and rotated into the work.
        if ($f[0] === '.') continue;
        if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $f)) continue;
        if (!is_file(IMAGES_DIR . '/' . $f)) continue;
        // Reject only genuinely empty files. An earlier 1KB floor here silently
        // dropped legitimate artwork: a quantised placeholder graphic can be
        // under 500 bytes. AppleDouble stubs are already excluded by name above.
        if ((int) @filesize(IMAGES_DIR . '/' . $f) < 1) continue;
        $out[] = $f;
    }
    natcasesort($out);
    return array_values($out);
}

/**
 * Fingerprint of the image set: filename + byte size, stat only.
 *
 * NOT a content hash: at 1342 images / ~200 MB that meant md5-ing the whole set
 * on every admin page load, which took long enough to time out. NOT mtime
 * either — rsync and tar rewrite it, so identical files would report as changed
 * and train you to ignore the one warning that says the live listing is about
 * to move.
 *
 * The blind spot is a replacement that keeps both the exact filename and the
 * exact byte count. prep-images.py numbers its output sequentially and re-encodes,
 * so that combination effectively cannot arise here.
 */
function images_signature(array $files): string {
    // Sorted first, so the signature describes the SET and is independent of
    // order. An era published with an explicit (interspersed) order would
    // otherwise hash differently from the same files in scan order, and report
    // as permanently pending. Order drift is tracked separately, in
    // pending_changes()->order_stale.
    sort($files);
    $parts = [];
    foreach ($files as $f) {
        $parts[] = $f . ':' . (int) @filesize(IMAGES_DIR . '/' . $f);
    }
    return sha1(implode('|', $parts));
}

function read_eras(): array { return read_json(data_path('eras.json'), ['eras' => []])['eras'] ?? []; }

function write_eras(array $eras): bool {
    return write_json_atomic(data_path('eras.json'), ['eras' => array_values($eras)]);
}

function current_era(?array $eras = null): ?array {
    $eras ??= read_eras();
    return $eras ? $eras[count($eras) - 1] : null;
}

/**
 * Open a new era from the images currently on disk.
 * Explicit by design: dropping files into images/ changes nothing until published,
 * so a half-finished upload can never alter a live listing.
 */
/**
 * Image classification, for interspersing. filename => "midjourney"|"placeholder".
 * Filenames are opaque numbers by design (see below), so type can't be inferred.
 */
function image_types(): array {
    return read_json(data_path('image-types.json'), []);
}

/**
 * Rotation order with the placeholders spread evenly through the generations
 * rather than clustered.
 *
 * Order is computed here and STORED IN THE ERA — it is deliberately not encoded
 * in the filenames. Renaming files to change their sequence would silently
 * falsify every published era, because an era records filenames: "0001.jpeg"
 * would still be listed, but would now be a different picture. Filenames are
 * permanent identity; sequence is per-era data.
 *
 * Deterministic for a given seed, so the "arbitrary" order is reproducible.
 */
function interspersed_order(array $files, int $seed = 1342): array {
    $types = image_types();
    $mj = $ph = [];
    foreach ($files as $f) {
        if (($types[$f] ?? 'midjourney') === 'placeholder') { $ph[] = $f; } else { $mj[] = $f; }
    }
    if (!$ph || !$mj) return $files;

    // Seeded Fisher-Yates; PHP's shuffle() ignores mt_srand on some builds.
    $shuffle = function (array $a) use (&$seed): array {
        mt_srand($seed);
        for ($i = count($a) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$a[$i], $a[$j]] = [$a[$j], $a[$i]];
        }
        return $a;
    };
    $mj = $shuffle($mj);
    $ph = $shuffle($ph);

    $total = count($mj) + count($ph);
    $slots = [];
    foreach (array_keys($ph) as $k) {
        $slots[(int) round(($k + 0.5) * $total / count($ph))] = true;
    }

    $out = []; $mi = 0; $pi = 0;
    for ($pos = 0; $pos < $total; $pos++) {
        if (isset($slots[$pos]) && $pi < count($ph)) $out[] = $ph[$pi++];
        elseif ($mi < count($mj))                     $out[] = $mj[$mi++];
        else                                          $out[] = $ph[$pi++];
    }
    return $out;
}

function publish_era(?int $startTs = null, string $note = '', ?int $rotate = null, ?array $order = null): array {
    $files = scan_images();
    if (!$files) return ['ok' => false, 'error' => 'images/ is empty — nothing to publish'];

    if ($order !== null) {
        // An order that isn't exactly the file set would drop or duplicate images.
        $a = $order; $b = $files; sort($a); sort($b);
        if ($a !== $b) {
            return ['ok' => false, 'error' => 'supplied order does not match images/ exactly ('
                . count($order) . ' vs ' . count($files) . ' files)'];
        }
        $files = $order;
    }

    $eras = read_eras();
    $start = $startTs ?? time();

    if ($eras) {
        $last = $eras[count($eras) - 1];
        if ($start <= (int) $last['start']) {
            return ['ok' => false, 'error' => 'new era must start after the current one ('
                . gmdate('Y-m-d H:i:s', (int) $last['start']) . 'Z)'];
        }
    }

    $era = [
        'id'        => count($eras),
        'ordered'   => $order !== null,
        'start'     => $start,
        'start_iso' => gmdate('Y-m-d\TH:i:s\Z', $start),
        'rotate'    => $rotate ?? ROTATE_SECONDS,
        'images'    => $files,
        'count'     => count($files),
        'sig'       => images_signature($files),
        'published' => gmdate('Y-m-d\TH:i:s\Z'),
        'note'      => $note,
    ];
    $eras[] = $era;
    if (!write_eras($eras)) return ['ok' => false, 'error' => 'could not write eras.json'];
    return ['ok' => true, 'era' => $era];
}

/** True when images/ no longer matches the published era — i.e. a publish is pending. */
function pending_changes(): array {
    $files = scan_images();
    $era   = current_era();
    if (!$era) {
        // Same capping as below: with no era published every file counts as
        // "added", and rendering 1342 filenames into the admin page (or a
        // terminal) is noise, not information.
        return ['pending' => (bool) $files, 'added' => array_slice($files, 0, 12),
                'removed' => [], 'added_n' => count($files), 'removed_n' => 0, 'files' => $files];
    }
    $added   = array_values(array_diff($files, $era['images']));
    $removed = array_values(array_diff($era['images'], $files));
    // Order can drift even when the file set is identical — a new era published
    // without an explicit order falls back to alphabetical, clustering
    // placeholders. Surface that as publishable, or there is no way to fix it.
    $wantOrder   = interspersed_order($files);
    $orderStale  = ($era['images'] ?? []) !== $wantOrder;

    return [
        'pending'     => images_signature($files) !== $era['sig'] || $orderStale,
        'files_changed' => images_signature($files) !== $era['sig'],
        'order_stale' => $orderStale,
        // Counts are what you act on; the full lists can run to four figures and
        // would otherwise be rendered in their entirety into the admin page.
        'added'       => array_slice($added, 0, 12),
        'removed'     => array_slice($removed, 0, 12),
        'added_n'     => count($added),
        'removed_n'   => count($removed),
        'files'       => $files,
    ];
}

function read_state(): array {
    return read_json(data_path('state.json'), ['frozen' => false]);
}

/**
 * THE central function: what was on screen at $ts?
 * Deterministic and reproducible by anyone holding eras.json.
 */
function resolve_at(int $ts, ?array $eras = null, ?array $state = null): array {
    $state ??= read_state();

    if (!empty($state['frozen'])) {
        return [
            'file'   => $state['frozen_file'],
            'idx'    => $state['frozen_idx'],
            'era'    => $state['frozen_era'],
            'frozen' => true,
            'reason' => 'frozen at sale (' . ($state['sale_iso'] ?? '?') . ')',
        ];
    }

    $eras ??= read_eras();
    if (!$eras) return ['file' => null, 'idx' => null, 'era' => null, 'frozen' => false, 'reason' => 'no era published'];

    $era = null;
    foreach ($eras as $e) { if ($ts >= (int) $e['start']) $era = $e; }
    if ($era === null) {
        return ['file' => null, 'idx' => null, 'era' => null, 'frozen' => false,
                'reason' => 'timestamp precedes the first era'];
    }

    $n = count($era['images']);
    if ($n === 0) return ['file' => null, 'idx' => null, 'era' => $era['id'], 'frozen' => false, 'reason' => 'era has no images'];

    $elapsed = $ts - (int) $era['start'];
    $idx = intdiv($elapsed, (int) $era['rotate']) % $n;

    return [
        'file'   => $era['images'][$idx],
        'idx'    => $idx,
        'era'    => $era['id'],
        'frozen' => false,
        'reason' => sprintf('era %d, slot %d of %d', $era['id'], $idx, $n),
        'slot_start' => (int) $era['start'] + intdiv($elapsed, (int) $era['rotate']) * (int) $era['rotate'],
        'rotate' => (int) $era['rotate'],
    ];
}

/**
 * Where a request came from. The artwork's own home page polls this endpoint to
 * show the rotation live, and those fetches must never be mistaken for a
 * viewer's look at the listing — see views_before(), which excludes them.
 *
 * 'ebay'    ebay.com or the ebaydesc.com description host
 * 'site'    the piece's own page on michaelneff.com (the live viewer)
 * 'unknown' no Referer — the iOS eBay app sends none, so this is NOT "not eBay"
 * 'other'   somewhere else entirely (someone else hotlinking the hotlink)
 */
function request_source(?string $referer): string {
    if ($referer === null || $referer === '') return 'unknown';
    $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
    if ($host === '') return 'other';
    if (str_contains($host, 'ebay')) return 'ebay';
    if (str_contains($host, 'michaelneff.com')) return 'site';
    return 'other';
}

function log_view(array $extra = []): void {
    $entry = array_merge([
        'iso'     => gmdate('Y-m-d\TH:i:s\Z'),
        'ts'      => time(),
        'ip'      => $_SERVER['REMOTE_ADDR']          ?? null,
        'xff'     => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        'ua'      => $_SERVER['HTTP_USER_AGENT']      ?? null,
        'referer' => $_SERVER['HTTP_REFERER']         ?? null,
        'src'     => request_source($_SERVER['HTTP_REFERER'] ?? null),
        'accept'  => $_SERVER['HTTP_ACCEPT']          ?? null,
        'lang'    => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null,
        'method'  => $_SERVER['REQUEST_METHOD']       ?? null,
        'uri'     => $_SERVER['REQUEST_URI']          ?? null,
        'https'   => !empty($_SERVER['HTTPS']),
    ], $extra);

    @file_put_contents(
        data_path('views-' . gmdate('Y-m-d') . '.jsonl'),
        json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

function is_bot(?string $ua): bool {
    $ua = strtolower((string) $ua);
    foreach (BOT_PATTERNS as $p) if ($p !== '' && str_contains($ua, $p)) return true;
    return false;
}

/** Admin password hash, stored outside the source so deploys never clobber it. */
function admin_hash(): ?string {
    $f = data_path('admin.hash');
    if (!is_readable($f)) return null;
    $h = trim((string) file_get_contents($f));
    return $h === '' ? null : $h;
}

/**
 * The buyer's last plausible look at the work before a sale.
 *
 * An eBay <img> is fetched once per page load and never updates — no JS runs in
 * a description — so whatever was served at the buyer's last page load stayed
 * frozen on their screen through browsing, checkout and confirmation. That fetch,
 * not the order timestamp, is what they were actually looking at when they bought.
 *
 * Attribution is inference, not proof: Safari IPs are masked by iCloud Private
 * Relay and the iOS eBay app sends no Referer, so we cannot prove a given request
 * was the buyer's. We therefore return candidates and let the record say so,
 * rather than asserting a single certain answer.
 *
 * Bots are excluded (Googlebot crawls within minutes of listing). Referer is NOT
 * used as a filter — ~30% of genuine eBay views carry none.
 */
function views_before(int $ts, int $window = 3600, int $limit = 8): array {
    $out = [];
    // A window can straddle midnight UTC, so check the prior day's file too.
    foreach ([gmdate('Y-m-d', $ts), gmdate('Y-m-d', $ts - 86400)] as $day) {
        $f = data_path('views-' . $day . '.jsonl');
        if (!is_readable($f)) continue;
        foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $r = json_decode($line, true);
            if (!is_array($r) || ($r['event'] ?? '') !== 'view') continue;
            if (is_bot($r['ua'] ?? null)) continue;
            // The home page polls this endpoint every 90s to show the rotation
            // live. Those are not looks at the listing and must never be
            // attributed to a buyer.
            if (($r['src'] ?? '') === 'site') continue;
            $t = (int) ($r['ts'] ?? 0);
            if ($t <= $ts && $t >= $ts - $window) $out[] = $r;
        }
    }
    usort($out, fn($a, $b) => ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));
    return array_slice($out, 0, $limit);
}

/** Convenience: the single most recent non-bot view before $ts, or null. */
function last_view_before(int $ts, int $window = 3600): ?array {
    return views_before($ts, $window, 1)[0] ?? null;
}

function human_gap(int $seconds): string {
    if ($seconds < 60) return $seconds . 's';
    $m = intdiv($seconds, 60); $s = $seconds % 60;
    if ($m < 60) return $m . 'm ' . $s . 's';
    return intdiv($m, 60) . 'h ' . ($m % 60) . 'm';
}
