<?php
/**
 * Clean slate — retire the test run and start the real one at era 0.
 *
 *   php tools/reset.php                  # show what would happen
 *   php tools/reset.php --confirm        # do it
 *   php tools/reset.php --confirm --keep-logs
 *
 * Nothing is destroyed: images, eras.json, state.json and view logs are MOVED
 * into data/retired-<timestamp>/. If you reset by mistake, move them back.
 *
 * Use this BEFORE the exhibition, to clear provisional images and the history
 * they generated. Do NOT use it mid-exhibition — retiring eras.json destroys the
 * record of what was displayed when, which is the evidence the work depends on.
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib.php';

$confirm  = in_array('--confirm', $argv, true);
$keepLogs = in_array('--keep-logs', $argv, true);

$images = scan_images();
$eras   = read_eras();
$state  = read_state();
$logs   = glob(DATA_DIR . '/views-*.jsonl') ?: [];
$views  = 0;
foreach ($logs as $f) $views += max(0, count(file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []));

echo "\nCurrent state\n-------------\n";
printf("  images        : %d\n", count($images));
printf("  eras          : %d%s\n", count($eras),
    $eras ? " (latest #{$eras[count($eras)-1]['id']}, since {$eras[count($eras)-1]['start_iso']})" : "");
printf("  frozen        : %s\n", !empty($state['frozen']) ? 'YES — ' . $state['frozen_file'] : 'no');
printf("  view log files: %d (%d views)%s\n", count($logs), $views, $keepLogs ? '  [will be KEPT]' : '');

if (!empty($state['frozen'])) {
    echo "\n  !! This piece is FROZEN — it looks like it has already sold.\n";
    echo "     Resetting would destroy the record of what was sold. Are you certain?\n";
}

if (!$confirm) {
    echo "\nDry run. Nothing changed.\n";
    echo "To proceed:  php tools/reset.php --confirm" . ($keepLogs ? " --keep-logs" : "") . "\n\n";
    exit(0);
}

$stamp   = gmdate('Ymd-His');
$archive = DATA_DIR . '/retired-' . $stamp;
if (!@mkdir($archive, 0755, true)) { fwrite(STDERR, "Could not create $archive\n"); exit(1); }

$moved = 0;
if ($images) {
    @mkdir($archive . '/images', 0755, true);
    foreach ($images as $f) {
        if (@rename(IMAGES_DIR . '/' . $f, $archive . '/images/' . $f)) $moved++;
    }
}
foreach (['eras.json', 'state.json'] as $f) {
    if (is_file(data_path($f))) @rename(data_path($f), $archive . '/' . $f);
}
if (!$keepLogs) {
    foreach ($logs as $f) @rename($f, $archive . '/' . basename($f));
}

echo "\nRetired to data/retired-$stamp/\n";
printf("  %d image(s), %d era(s), %d log file(s)\n", $moved, count($eras), $keepLogs ? 0 : count($logs));
echo "\nClean slate. Next:\n";
echo "  1. python3 tools/prep-images.py /path/to/finals/*.jpg   (on your Mac)\n";
echo "  2. ./tools/deploy.sh --images\n";
echo "  3. php tools/publish-era.php \"era 0 — final images\"     (or click Publish in admin)\n\n";
echo "Note: until an era is published, wysiwyg.jpeg returns 503. Do this before the listing goes up.\n";
