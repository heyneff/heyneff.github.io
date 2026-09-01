<?php
/**
 * Prune server-side images that are no longer in the local set.
 *
 * `tar` extracts over the top and never deletes, so without this a removed test
 * image lingers on the server — and scan_images() would fold it straight into
 * the next published era.
 *
 *   php tools/sync-images.php data/.manifest [--force]
 *
 * Refuses to delete a file a PUBLISHED era still references: a freeze on it
 * would 503, and its admin preview would 404. --force overrides.
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib.php';

$manifestPath = $argv[1] ?? '';
$force = in_array('--force', $argv, true);

if (!is_readable($manifestPath)) { fwrite(STDERR, "  manifest missing; skipping prune\n"); exit(0); }

$keep = array_values(array_filter(array_map('trim', file($manifestPath))));
if (!$keep) { fwrite(STDERR, "  manifest empty; refusing to prune\n"); exit(0); }

$have  = array_values(array_diff((array) scandir(IMAGES_DIR), ['.', '..']));
$extra = array_values(array_diff($have, $keep));

if (!$extra) { echo "  images in sync, nothing to remove\n"; exit(0); }

// Which files are still promised by a published era?
$refs = [];
foreach (read_eras() as $e) {
    foreach ($e['images'] as $f) $refs[$f] = $e['id'];
}

$blocked = $free = [];
foreach ($extra as $f) { isset($refs[$f]) ? $blocked[] = $f : $free[] = $f; }

foreach ($free as $f) { @unlink(IMAGES_DIR . '/' . $f); echo "  removed $f\n"; }

if ($blocked) {
    if ($force) {
        foreach ($blocked as $f) {
            @unlink(IMAGES_DIR . '/' . $f);
            echo "  removed $f (FORCED — era {$refs[$f]} referenced it)\n";
        }
    } else {
        echo "\n  KEPT " . count($blocked) . " file(s) a published era still references:\n";
        foreach ($blocked as $f) echo "    $f  (era {$refs[$f]})\n";
        echo "  Publishing a new era supersedes them for future views, but past\n";
        echo "  timestamps still resolve to them — so they are kept by default.\n";
        echo "  Options: --force to delete anyway, or `php tools/reset.php` to wipe all history.\n";
    }
}
