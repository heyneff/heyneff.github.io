<?php
// Old-school image-based hit counter. Embed with:
//   <img src="https://yourdomain.com/counter.php?id=my-listing">
// Each request increments a per-id count stored in counts/ and returns a
// freshly-rendered 7-segment LED style PNG of the current total.

$DIGITS = 6;               // zero-padded width; grows automatically if exceeded
$START_OFFSET = 0;         // add to the real count, e.g. 1000 to look established
$COUNTS_DIR = __DIR__ . '/counts';

$id = isset($_GET['id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['id']) : 'default';
if ($id === '') {
    $id = 'default';
}

if (!is_dir($COUNTS_DIR)) {
    mkdir($COUNTS_DIR, 0775, true);
}

$file = $COUNTS_DIR . '/' . $id . '.count';

$fp = fopen($file, 'c+');
if ($fp === false) {
    http_response_code(500);
    exit;
}
flock($fp, LOCK_EX);
$contents = stream_get_contents($fp);
$count = ($contents === false || trim($contents) === '') ? 0 : (int) trim($contents);
$count++;
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, (string) $count);
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

$display = $count + $START_OFFSET;
$str = str_pad((string) $display, $DIGITS, '0', STR_PAD_LEFT);

// --- render as a 7-segment LED PNG ---

$digitW = 24;
$digitH = 44;
$thick = 6;
$gap = 6;
$pad = 10;

$numDigits = strlen($str);
$imgW = $pad * 2 + $numDigits * $digitW + ($numDigits - 1) * $gap;
$imgH = $pad * 2 + $digitH;

$im = imagecreatetruecolor($imgW, $imgH);
$black = imagecolorallocate($im, 0, 0, 0);
$litGreen = imagecolorallocate($im, 51, 255, 68);
$dimGreen = imagecolorallocate($im, 17, 51, 22);
imagefilledrectangle($im, 0, 0, $imgW, $imgH, $black);

// segment order: a(top) b(top-right) c(bottom-right) d(bottom) e(bottom-left) f(top-left) g(middle)
$segments = [
    '0' => [1, 1, 1, 1, 1, 1, 0],
    '1' => [0, 1, 1, 0, 0, 0, 0],
    '2' => [1, 1, 0, 1, 1, 0, 1],
    '3' => [1, 1, 1, 1, 0, 0, 1],
    '4' => [0, 1, 1, 0, 0, 1, 1],
    '5' => [1, 0, 1, 1, 0, 1, 1],
    '6' => [1, 0, 1, 1, 1, 1, 1],
    '7' => [1, 1, 1, 0, 0, 0, 0],
    '8' => [1, 1, 1, 1, 1, 1, 1],
    '9' => [1, 1, 1, 1, 0, 1, 1],
];

function drawHSeg($im, $x1, $x2, $y, $t, $color)
{
    $pts = [
        $x1, $y,
        $x1 + $t / 2, $y - $t / 2,
        $x2 - $t / 2, $y - $t / 2,
        $x2, $y,
        $x2 - $t / 2, $y + $t / 2,
        $x1 + $t / 2, $y + $t / 2,
    ];
    imagefilledpolygon($im, $pts, $color);
}

function drawVSeg($im, $x, $y1, $y2, $t, $color)
{
    $pts = [
        $x, $y1,
        $x + $t / 2, $y1 + $t / 2,
        $x + $t / 2, $y2 - $t / 2,
        $x, $y2,
        $x - $t / 2, $y2 - $t / 2,
        $x - $t / 2, $y1 + $t / 2,
    ];
    imagefilledpolygon($im, $pts, $color);
}

for ($i = 0; $i < $numDigits; $i++) {
    $ch = $str[$i];
    $seg = $segments[$ch] ?? [0, 0, 0, 0, 0, 0, 0];
    $ox = $pad + $i * ($digitW + $gap);
    $oy = $pad;
    $midY = $oy + $digitH / 2;

    drawHSeg($im, $ox, $ox + $digitW, $oy, $thick, $seg[0] ? $litGreen : $dimGreen);
    drawHSeg($im, $ox, $ox + $digitW, $midY, $thick, $seg[6] ? $litGreen : $dimGreen);
    drawHSeg($im, $ox, $ox + $digitW, $oy + $digitH, $thick, $seg[3] ? $litGreen : $dimGreen);

    drawVSeg($im, $ox, $oy, $midY, $thick, $seg[5] ? $litGreen : $dimGreen);
    drawVSeg($im, $ox + $digitW, $oy, $midY, $thick, $seg[1] ? $litGreen : $dimGreen);
    drawVSeg($im, $ox, $midY, $oy + $digitH, $thick, $seg[4] ? $litGreen : $dimGreen);
    drawVSeg($im, $ox + $digitW, $midY, $oy + $digitH, $thick, $seg[2] ? $litGreen : $dimGreen);
}

header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
imagepng($im);
