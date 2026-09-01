<?php
/**
 * Publish an era from the CLI. Same operation as the admin button, for use
 * before a password is set or from a script.
 *
 *   php tools/publish-era.php  ["note"]  [--rotate=600]  [--start="2026-09-01 12:00:00"]
 *   php tools/publish-era.php --status
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib.php';

$args = array_slice($argv, 1);
$opt  = ['note' => '', 'rotate' => null, 'start' => null, 'status' => false, 'plain' => false];
foreach ($args as $a) {
    if ($a === '--status')                        $opt['status'] = true;
    elseif (str_starts_with($a, '--rotate='))     $opt['rotate'] = (int) substr($a, 9);
    elseif (str_starts_with($a, '--start='))      $opt['start']  = strtotime(substr($a, 8)) ?: null;
    elseif ($a === '--plain')                     $opt['plain']  = true;   // alphabetical, no interspersing
    elseif (!str_starts_with($a, '--'))           $opt['note']   = $a;
}

$p = pending_changes();
$e = current_era();

echo "images/ on disk : " . count($p['files']) . "\n";
echo "current era     : " . ($e ? "#{$e['id']}, {$e['count']} images, every " . ($e['rotate'] / 60) . "m, since {$e['start_iso']}" : "none") . "\n";
$more = fn(array $shown, int $n) => implode(', ', $shown) . ($n > count($shown) ? " … (+" . ($n - count($shown)) . " more)" : "");
if (!empty($p['added_n']))   echo "added           : " . $p['added_n'] . " — " . $more($p['added'], $p['added_n']) . "\n";
if (!empty($p['removed_n'])) echo "removed         : " . $p['removed_n'] . " — " . $more($p['removed'], $p['removed_n']) . "\n";
echo "pending publish : " . ($p['pending'] ? "YES" : "no")
   . (!empty($p['order_stale']) ? "  (order differs from interspersed)" : "") . "\n";

$now = resolve_at(time());
echo "showing now     : " . ($now['file'] ?? '—') . "  ({$now['reason']})\n";

if ($opt['status']) exit(0);
if (!$p['pending']) { echo "\nNothing to publish.\n"; exit(0); }

// Placeholders spread through the generations rather than clustered. The order
// is stored in the era, never baked into filenames — see interspersed_order().
$order = $opt['plain'] ? null : interspersed_order(scan_images());
if ($order) {
    $t = image_types();
    $n = count(array_filter($order, fn($f) => ($t[$f] ?? '') === 'placeholder'));
    echo "order           : interspersed, {$n} placeholder(s) every ~"
       . round(count($order) / max($n, 1)) . " images\n";
}
$r = publish_era($opt['start'], $opt['note'], $opt['rotate'], $order);
if (!$r['ok']) { fwrite(STDERR, "\nFAILED: {$r['error']}\n"); exit(1); }

$e = $r['era'];
echo "\nPublished era {$e['id']}: {$e['count']} images, every " . ($e['rotate'] / 60)
   . " min, from {$e['start_iso']} (full cycle "
   . round($e['count'] * $e['rotate'] / 3600, 2) . "h)\n";
$now = resolve_at(time());
echo "now showing     : {$now['file']}  ({$now['reason']})\n";
