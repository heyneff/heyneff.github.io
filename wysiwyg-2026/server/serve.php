<?php
/**
 * WYSIWYG (2026) — the hotlinked image endpoint.
 *
 * Reached as /wysiwyg.jpeg via .htaccess rewrite. An eBay listing points an
 * <img> here; the src never changes, the bytes do.
 *
 * Measured 2026-08-20: eBay neither caches nor proxies description images, so
 * every viewer's load reaches this script and is recorded.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

// A viewer who navigates away mid-transfer still saw the work; still count it.
ignore_user_abort(true);

$now = time();
$r   = resolve_at($now);

if ($r['file'] === null) {
    log_view(['event' => 'unresolved', 'reason' => $r['reason']]);
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    exit("wysiwyg: {$r['reason']}\n");
}

$path = IMAGES_DIR . '/' . $r['file'];
if (!is_readable($path)) {
    log_view(['event' => 'missing_file', 'file' => $r['file'], 'era' => $r['era']]);
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit("wysiwyg: image missing\n");
}

// Record before serving: a transfer that dies mid-flight is still a view.
log_view([
    'event'  => 'view',
    'file'   => $r['file'],
    'idx'    => $r['idx'],
    'era'    => $r['era'],
    'frozen' => $r['frozen'],
]);

$mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    default => 'image/jpeg',
};

// No ETag and no Last-Modified, deliberately: either invites a conditional
// request answered 304, serving nothing while the rotation had already moved on
// — desyncing what the viewer sees from what the log says was shown.
header_remove('ETag');
header_remove('Last-Modified');

header('Content-Type: ' . $mime);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('X-Accel-Expires: 0');                 // honoured by Bluehost's nginx layer
header('Content-Length: ' . (string) filesize($path));

// Which image this is, so the live viewer can name it without a second request.
header('X-Image: ' . $r['file']);
header('X-Era: ' . $r['era']);
header('X-Slot: ' . $r['idx']);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Expose-Headers: X-Image, X-Era, X-Slot');

readfile($path);
