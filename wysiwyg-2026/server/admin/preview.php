<?php
/** Serves an image to the admin console only. Never logs — admin previews are
 *  not views of the artwork and must not pollute the record. */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$f = basename((string) ($_GET['f'] ?? ''));           // basename defeats traversal
$path = IMAGES_DIR . '/' . $f;

if ($f === '' || !preg_match('/\.(jpe?g|png|gif|webp)$/i', $f) || !is_readable($path)) {
    http_response_code(404); exit;
}
$mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
    'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', default => 'image/jpeg',
};
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=60');
header('Content-Length: ' . (string) filesize($path));
readfile($path);
