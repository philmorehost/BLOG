<?php
// Public image delivery independent of static-file rewrites, hotlink handlers,
// and a separate web-server user's filesystem permissions. No DB/session needed.
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'HEAD') {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}

function media_not_found() {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    exit('Image not found.');
}

$path = $_GET['path'] ?? '';
if (!is_string($path) || strpos($path, "\0") !== false || strpos($path, '\\') !== false) media_not_found();
// Do not accept arbitrary files, remote URLs, or executable formats such as SVG.
if (!preg_match('#^assets/uploads/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_.-]+\.(?:jpe?g|png|gif|webp|ico)$#iD', $path)) media_not_found();
$base = realpath(__DIR__ . '/assets/uploads');
$file = realpath(__DIR__ . '/' . $path);
if ($base === false || $file === false || strpos($file, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($file) || !is_readable($file)) media_not_found();

$info = @getimagesize($file);
$types = [
    IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png',
    IMAGETYPE_GIF => 'image/gif', IMAGETYPE_WEBP => 'image/webp',
    IMAGETYPE_ICO => 'image/x-icon'
];
if ($info === false || !isset($types[$info[2]])) media_not_found();
$stream = @fopen($file, 'rb');
if ($stream === false) media_not_found();
$stat = fstat($stream);
$etag = '"' . dechex($stat['mtime']) . '-' . dechex($stat['size']) . '"';
header('Content-Type: ' . $types[$info[2]]);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600, must-revalidate');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $stat['mtime']) . ' GMT');
if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    fclose($stream);
    http_response_code(304);
    exit;
}
header('Content-Length: ' . $stat['size']);
if ($method !== 'HEAD') fpassthru($stream);
fclose($stream);
