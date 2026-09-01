<?php
/**
 * WYSIWYG (2026) — admin console.
 * Status, era publishing (add images anytime), sale freeze, logs, timestamp lookup.
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$msg = ''; $msgType = 'ok';

// ---------- actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'login') {
    if (!check_csrf()) {
        $msg = 'Stale form token — reload and retry.'; $msgType = 'err';
    } else {
        switch ($_POST['action']) {

            case 'publish_era':
                $note = trim((string) ($_POST['note'] ?? ''));
                // Must match tools/publish-era.php. Without an explicit order,
                // publish_era() falls back to alphabetical, which clusters every
                // newly-added placeholder at the end of the cycle — era 2 shipped
                // that way because this button skipped the ordering step.
                $r = publish_era(null, $note, null, interspersed_order(scan_images()));
                if ($r['ok']) {
                    $e = $r['era'];
                    $t = image_types();
                    $np = count(array_filter($e['images'], fn($f) => ($t[$f] ?? '') === 'placeholder'));
                    $msg = "Published era {$e['id']} — {$e['count']} images, rotating every "
                         . ($e['rotate'] / 60) . " min, from {$e['start_iso']}."
                         . ($np ? " {$np} placeholders interspersed, one every ~"
                                . round($e['count'] / $np) . " images." : '');
                } else { $msg = $r['error']; $msgType = 'err'; }
                break;

            case 'freeze':
                $saleTs = strtotime((string) ($_POST['sale_time'] ?? ''));
                if ($saleTs === false) { $msg = 'Could not parse that sale time.'; $msgType = 'err'; break; }
                // Resolve against the SCHEDULE, ignoring any existing freeze, so the
                // frozen image is the one the clock actually served at the sale.
                $r = resolve_at($saleTs, null, ['frozen' => false]);
                if ($r['file'] === null) { $msg = "Cannot resolve that time: {$r['reason']}"; $msgType = 'err'; break; }

                // Two different images can both honestly be called "the one that
                // sold": the one the ledger timestamps, and the one that was
                // actually on the buyer's screen. Record both, and the gap.
                $lastView  = last_view_before($saleTs);
                $candidates = array_map(fn($v) => [
                    'ts' => $v['ts'] ?? null, 'iso' => $v['iso'] ?? null, 'file' => $v['file'] ?? null,
                    'ip' => $v['ip'] ?? null, 'referer' => $v['referer'] ?? null, 'ua' => $v['ua'] ?? null,
                ], views_before($saleTs));

                write_json_atomic(data_path('state.json'), [
                    'frozen'      => true,
                    'frozen_file' => $r['file'],
                    'frozen_idx'  => $r['idx'],
                    'frozen_era'  => $r['era'],
                    'sale_ts'     => $saleTs,
                    'sale_iso'    => gmdate('Y-m-d\TH:i:s\Z', $saleTs),
                    'frozen_at'   => gmdate('Y-m-d\TH:i:s\Z'),
                    'note'        => trim((string) ($_POST['freeze_note'] ?? '')),

                    // What the buyer was most likely looking at. Inference, not
                    // proof — Private Relay masks Safari IPs and the iOS app
                    // sends no Referer, so candidates are kept for the record.
                    'last_view_file' => $lastView['file'] ?? null,
                    'last_view_ts'   => $lastView['ts'] ?? null,
                    'last_view_iso'  => $lastView['iso'] ?? null,
                    'gap_seconds'    => $lastView ? $saleTs - (int) $lastView['ts'] : null,
                    'diverged'       => $lastView ? ($lastView['file'] !== $r['file']) : null,
                    'view_candidates'=> $candidates,
                ]);

                $msg = "FROZEN on {$r['file']} — displaying at " . gmdate('Y-m-d H:i:s', $saleTs) . 'Z.';
                if ($lastView) {
                    $gap = human_gap($saleTs - (int) $lastView['ts']);
                    $msg .= $lastView['file'] === $r['file']
                        ? " Last view {$gap} earlier showed the same image."
                        : " Note: the last view {$gap} earlier showed {$lastView['file']} — the buyer's screen and the ledger disagree, and both are recorded.";
                } else {
                    $msg .= ' No non-bot view found in the preceding hour.';
                }
                break;

            case 'unfreeze':
                write_json_atomic(data_path('state.json'), ['frozen' => false]);
                $msg = 'Unfrozen — rotation resumed.';
                break;
        }
    }
}

// ---------- CSV export ----------
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="wysiwyg-views.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['iso','ts','file','idx','era','ip','ua','referer','https','bot']);
    foreach (glob(DATA_DIR . '/views-*.jsonl') ?: [] as $f) {
        foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $r = json_decode($line, true); if (!is_array($r)) continue;
            fputcsv($out, [$r['iso'] ?? '', $r['ts'] ?? '', $r['file'] ?? '', $r['idx'] ?? '',
                           $r['era'] ?? '', $r['ip'] ?? '', $r['ua'] ?? '', $r['referer'] ?? '',
                           !empty($r['https']) ? 1 : 0, is_bot($r['ua'] ?? null) ? 1 : 0]);
        }
    }
    exit;
}

// ---------- gather ----------
$now     = time();
$eras    = read_eras();
$era     = current_era($eras);
$state   = read_state();
$cur     = resolve_at($now);
$pending = pending_changes();

$rows = [];
foreach (glob(DATA_DIR . '/views-*.jsonl') ?: [] as $f) {
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $r = json_decode($line, true);
        if (is_array($r) && ($r['event'] ?? '') === 'view') $rows[] = $r;
    }
}
usort($rows, fn($a, $b) => ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));

$human = array_values(array_filter($rows, fn($r) => !is_bot($r['ua'] ?? null)));
$bots  = count($rows) - count($human);
$perImage = [];
foreach ($human as $r) { $f = $r['file'] ?? '?'; $perImage[$f] = ($perImage[$f] ?? 0) + 1; }
arsort($perImage);

// timestamp lookup
$lookup = null;
if (($q = trim((string) ($_GET['at'] ?? ''))) !== '') {
    $ts = ctype_digit($q) ? (int) $q : (int) strtotime($q);
    $lookup = $ts > 0
        ? ['ts' => $ts, 'iso' => gmdate('Y-m-d\TH:i:s\Z', $ts),
           'r' => resolve_at($ts, $eras, ['frozen' => false]),
           'last' => last_view_before($ts)]
        : ['error' => 'Could not parse "' . $q . '"'];
}

$nextIn = isset($cur['slot_start']) ? ($cur['slot_start'] + $cur['rotate'] - $now) : null;
function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
?><!doctype html><html><head><meta charset="utf-8"><title>WYSIWYG admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
 :root{--bg:#303030;--card:#3a3a3a;--line:#4a4a4a;--fg:#e4e4e4;--dim:#9a9a9a;--accent:#6b8cff}
 *{box-sizing:border-box}
 body{background:var(--bg);color:var(--fg);font:14px/1.55 -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
      margin:0;padding:24px}
 .wrap{max-width:1080px;margin:0 auto}
 h1{font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:var(--dim);margin:0 0 20px;font-weight:600}
 h2{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--dim);margin:0 0 12px;font-weight:600}
 .card{background:var(--card);border-radius:8px;padding:18px;margin-bottom:16px}
 .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px}
 .big{font-size:26px;font-weight:600;line-height:1.15;word-break:break-all}
 .dim{color:var(--dim)}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px}
 table{width:100%;border-collapse:collapse;font-size:12px}
 th{text-align:left;color:var(--dim);font-weight:600;padding:6px 8px;border-bottom:1px solid var(--line);
    position:sticky;top:0;background:var(--card)}
 td{padding:5px 8px;border-bottom:1px solid #444;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:250px}
 .scroll{max-height:420px;overflow:auto}
 input,button,textarea{font:inherit;border-radius:5px;border:1px solid #555;background:#2b2b2b;color:var(--fg);padding:8px 10px}
 button{background:var(--accent);border:0;color:#fff;cursor:pointer;font-weight:500}
 button.warn{background:#c0392b}button.ghost{background:#555}
 .msg{padding:11px 14px;border-radius:6px;margin-bottom:16px;background:#2d4a2d;border:1px solid #4a7a4a}
 .msg.err{background:#4a2d2d;border-color:#7a4a4a}
 .pill{display:inline-block;padding:2px 9px;border-radius:99px;font-size:11px;font-weight:600;letter-spacing:.04em}
 .pill.live{background:#2d5a2d;color:#9f9}.pill.frozen{background:#5a4a2d;color:#fc6}
 .thumb{width:100%;border-radius:6px;border:1px solid var(--line);display:block;background:#222}
 a{color:var(--accent)}
 .row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
 label{display:block;font-size:11px;color:var(--dim);margin-bottom:4px;text-transform:uppercase;letter-spacing:.06em}
</style></head><body><div class="wrap">

<h1>WYSIWYG &nbsp;·&nbsp; <span class="dim">wysiwyg.michaelneff.com</span>
  &nbsp;<a href="?logout=1" style="float:right;font-size:11px">Log out</a></h1>

<?php if ($msg): ?><div class="msg <?= $msgType === 'err' ? 'err' : '' ?>"><?= h($msg) ?></div><?php endif; ?>

<!-- ============ NOW ============ -->
<div class="card">
  <h2>Showing right now
    <?php if ($state['frozen'] ?? false): ?><span class="pill frozen">FROZEN</span>
    <?php else: ?><span class="pill live">ROTATING</span><?php endif; ?>
  </h2>
  <div class="grid">
    <div>
      <div class="big"><?= h($cur['file'] ?? '—') ?></div>
      <div class="dim mono" style="margin-top:6px"><?= h($cur['reason']) ?></div>
      <?php if ($nextIn !== null && !($state['frozen'] ?? false)): ?>
        <div class="dim" style="margin-top:10px">Next change in
          <strong><?= intdiv($nextIn, 60) ?>m <?= $nextIn % 60 ?>s</strong></div>
      <?php endif; ?>
      <?php if ($state['frozen'] ?? false): ?>
        <div class="dim mono" style="margin-top:10px">
          Sale: <?= h($state['sale_iso'] ?? '?') ?><br>
          <?= h($state['note'] ?? '') ?>
        </div>
      <?php endif; ?>
      <div class="mono dim" style="margin-top:12px">
        Now <?= gmdate('Y-m-d H:i:s') ?>Z &nbsp;·&nbsp; unix <?= $now ?>
      </div>
    </div>
    <div><?php if ($cur['file']): ?>
      <img class="thumb" src="preview.php?f=<?= urlencode($cur['file']) ?>" alt="">
    <?php endif; ?></div>
  </div>
</div>

<!-- ============ IMAGES / ERAS ============ -->
<div class="card">
  <h2>Image set</h2>
  <?php if ($era): ?>
    <p style="margin:0 0 10px">Era <strong><?= (int) $era['id'] ?></strong> ·
      <strong><?= (int) $era['count'] ?></strong> images ·
      every <strong><?= (int) $era['rotate'] / 60 ?> min</strong> ·
      since <span class="mono"><?= h($era['start_iso']) ?></span>
      <?= $era['note'] ? '· ' . h($era['note']) : '' ?>
      <br><span class="dim">Full cycle:
        <?php $cyc = $era['count'] * $era['rotate'];
              echo $cyc >= 172800 ? round($cyc/86400, 1) . ' days'
                 : ($cyc >= 7200 ? round($cyc/3600, 1) . ' hours' : round($cyc/60) . ' min'); ?>
        · each image airs once per cycle</span></p>
  <?php else: ?>
    <p class="dim" style="margin:0 0 10px">No era published yet.</p>
  <?php endif; ?>

  <?php if ($pending['pending']): ?>
    <div class="msg" style="background:#4a4020;border-color:#7a6a30">
      <strong>Unpublished changes in images/</strong><br>
      <?php if (!empty($pending['added_n'])): ?>
        <strong><?= (int) $pending['added_n'] ?></strong> added:
        <span class="mono"><?= h(implode(', ', $pending['added'])) ?><?= $pending['added_n'] > count($pending['added']) ? ' …' : '' ?></span><br>
      <?php endif; ?>
      <?php if (!empty($pending['removed_n'])): ?>
        <strong><?= (int) $pending['removed_n'] ?></strong> removed:
        <span class="mono"><?= h(implode(', ', $pending['removed'])) ?><?= $pending['removed_n'] > count($pending['removed']) ? ' …' : '' ?></span><br>
      <?php endif; ?>
      <span class="dim">Nothing changes on the live listing until you publish.</span>
    </div>
    <form method="post" class="row">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="publish_era">
      <div style="flex:1;min-width:220px"><label>Note (optional)</label>
        <input name="note" placeholder="e.g. added final 4 images" style="width:100%"></div>
      <button>Publish new era (<?= count($pending['files']) ?> images)</button>
    </form>
  <?php else: ?>
    <p class="dim" style="margin:0">images/ matches the published era. Drop new files in
      <span class="mono">images/</span> and reload to publish them.</p>
  <?php endif; ?>

  <?php if ($eras): ?>
    <div class="scroll" style="margin-top:16px;max-height:200px"><table>
      <tr><th>Era</th><th>From (UTC)</th><th>Images</th><th>Rotate</th><th>Note</th></tr>
      <?php foreach (array_reverse($eras) as $e): ?>
        <tr><td><?= (int) $e['id'] ?></td><td class="mono"><?= h($e['start_iso']) ?></td>
            <td><?= (int) $e['count'] ?></td><td><?= (int) $e['rotate'] / 60 ?>m</td>
            <td><?= h($e['note']) ?></td></tr>
      <?php endforeach; ?>
    </table></div>
  <?php endif; ?>
</div>

<!-- ============ LOOKUP ============ -->
<div class="card">
  <h2>What was showing at…</h2>
  <form method="get" class="row">
    <div style="flex:1;min-width:240px"><label>Time (any format, or a unix timestamp) — UTC</label>
      <input name="at" value="<?= h($_GET['at'] ?? '') ?>" placeholder="2026-09-14 18:42:00" style="width:100%"></div>
    <button>Look up</button>
  </form>
  <?php if ($lookup): ?>
    <?php if (isset($lookup['error'])): ?>
      <p class="msg err" style="margin-top:14px"><?= h($lookup['error']) ?></p>
    <?php else: $lr = $lookup['r']; ?>
      <div class="grid" style="margin-top:16px">
        <div>
          <div class="big"><?= h($lr['file'] ?? '—') ?></div>
          <div class="dim mono" style="margin-top:6px">
            <?= h($lookup['iso']) ?> · unix <?= $lookup['ts'] ?><br><?= h($lr['reason']) ?>
          </div>
          <?php if (!empty($lookup['last'])): $lv = $lookup['last'];
                  $gap = $lookup['ts'] - (int) $lv['ts']; ?>
            <div style="margin-top:12px;padding:10px;border-radius:6px;background:#2b2b2b">
              <div class="dim" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em">
                Last view before this moment</div>
              <div class="mono" style="margin-top:4px">
                <?= h($lv['file']) ?> · <?= h(human_gap($gap)) ?> earlier
              </div>
              <?php if ($lv['file'] !== $lr['file']): ?>
                <div style="color:#fc6;margin-top:6px;font-size:12px">
                  Differs from the image at this timestamp — the buyer's screen and
                  the ledger would disagree. Both get recorded on freeze.</div>
              <?php else: ?>
                <div class="dim" style="margin-top:6px;font-size:12px">Same image — screen and ledger agree.</div>
              <?php endif; ?>
            </div>
          <?php elseif (($_GET['at'] ?? '') !== ''): ?>
            <div class="dim" style="margin-top:12px;font-size:12px">No non-bot view logged in the preceding hour.</div>
          <?php endif; ?>
          <?php if ($lr['file']): ?>
          <form method="post" style="margin-top:14px">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="freeze">
            <input type="hidden" name="sale_time" value="<?= h($lookup['ts']) ?>">
            <label>Freeze permanently on this image — note</label>
            <div class="row">
              <input name="freeze_note" placeholder="eBay order #…" style="flex:1;min-width:180px">
              <button class="warn" onclick="return confirm('Freeze the listing permanently on <?= h($lr['file']) ?>?')">
                Freeze on this</button>
            </div>
          </form>
          <?php endif; ?>
        </div>
        <div><?php if ($lr['file']): ?>
          <img class="thumb" src="preview.php?f=<?= urlencode($lr['file']) ?>" alt="">
        <?php endif; ?></div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
  <p class="dim" style="margin:14px 0 0;font-size:12px">
    Paste the eBay order timestamp here at sale. The result is derived from the clock and the
    published era, so it is reproducible by anyone holding <span class="mono">eras.json</span> —
    it does not depend on the logs.</p>
</div>

<!-- ============ FREEZE STATE ============ -->
<?php if ($state['frozen'] ?? false): ?>
<div class="card">
  <h2>Freeze</h2>
  <p style="margin:0 0 12px">Locked on <strong><?= h($state['frozen_file']) ?></strong>
    since <span class="mono"><?= h($state['frozen_at']) ?></span>.</p>

  <?php if (!empty($state['last_view_file'])): ?>
    <table style="margin-bottom:14px;max-width:640px">
      <tr><th>What</th><th>Image</th><th>When (UTC)</th></tr>
      <tr><td>Ledger — eBay order time</td>
          <td class="mono"><strong><?= h($state['frozen_file']) ?></strong></td>
          <td class="mono"><?= h($state['sale_iso']) ?></td></tr>
      <tr><td>Screen — buyer's last view</td>
          <td class="mono"><?= h($state['last_view_file']) ?></td>
          <td class="mono"><?= h($state['last_view_iso']) ?></td></tr>
    </table>
    <p style="margin:0 0 12px">
      Gap <strong><?= h(human_gap((int) $state['gap_seconds'])) ?></strong> —
      <?php if (!empty($state['diverged'])): ?>
        <span style="color:#fc6">the two disagree.</span>
        The buyer's screen showed <span class="mono"><?= h($state['last_view_file']) ?></span>
        while the transaction records <span class="mono"><?= h($state['frozen_file']) ?></span>.
      <?php else: ?>
        both agree.
      <?php endif; ?>
    </p>
    <p class="dim" style="font-size:12px;margin:0 0 12px">
      Attribution is inference: iCloud Private Relay masks Safari IPs and the iOS
      eBay app sends no Referer, so the last view cannot be *proved* to be the
      buyer's. <?= count($state['view_candidates'] ?? []) ?> candidate view(s) stored in
      <span class="mono">data/state.json</span>.</p>
  <?php endif; ?>
  <form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>">
    <input type="hidden" name="action" value="unfreeze">
    <button class="ghost" onclick="return confirm('Resume rotation?')">Unfreeze</button></form>
</div>
<?php endif; ?>

<!-- ============ VIEWS ============ -->
<div class="card">
  <h2>Views</h2>
  <div class="grid" style="margin-bottom:16px">
    <div><div class="big"><?= count($human) ?></div><div class="dim">human views</div></div>
    <div><div class="big"><?= $bots ?></div><div class="dim">bot / crawler</div></div>
    <div><div class="big"><?= count(array_unique(array_column($human, 'ip'))) ?></div>
         <div class="dim">distinct IPs <span style="font-size:11px">(Safari masked by Private Relay)</span></div></div>
    <div><a href="?export=csv"><button class="ghost">Export CSV</button></a></div>
  </div>

  <?php if ($perImage): ?>
    <table style="margin-bottom:16px"><tr><th>Most-viewed image</th><th>Human views</th></tr>
    <?php foreach (array_slice($perImage, 0, 15, true) as $f => $c): ?>
      <tr><td class="mono"><?= h($f) ?></td><td><?= $c ?></td></tr>
    <?php endforeach; ?></table>
    <p class="dim" style="margin:-6px 0 16px;font-size:12px">
      Top 15 of <?= count($perImage) ?> images seen so far
      (<?= $era ? (int) $era['count'] : 0 ?> in the set). Full detail in the CSV.</p>
  <?php endif; ?>

  <div class="scroll"><table>
    <tr><th>UTC</th><th>Image</th><th>Era</th><th>IP</th><th>Referer</th><th>User-Agent</th></tr>
    <?php foreach (array_slice($rows, 0, 400) as $r): ?>
      <tr<?= is_bot($r['ua'] ?? null) ? ' style="opacity:.45"' : '' ?>>
        <td class="mono"><?= h(substr((string) ($r['iso'] ?? ''), 0, 19)) ?></td>
        <td class="mono"><?= h($r['file'] ?? '') ?></td>
        <td><?= h($r['era'] ?? '') ?></td>
        <td class="mono"><?= h($r['ip'] ?? '') ?></td>
        <td class="mono"><?= h($r['referer'] ?? '—') ?></td>
        <td class="mono" title="<?= h($r['ua'] ?? '') ?>"><?= h(substr((string) ($r['ua'] ?? ''), 0, 60)) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <p class="dim" style="margin:12px 0 0;font-size:12px">
    Showing newest 400. Bot rows dimmed but retained — nothing is discarded.
    The iOS eBay app sends no Referer, so “—” is not the same as “not from eBay”.</p>
</div>

</div></body></html>
