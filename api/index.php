<?php
// Vercel Serverless Router
date_default_timezone_set('Asia/Jakarta');

// Get request path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

// Normalize path
$file = ltrim($path, '/');

// Remove api/ prefix if present
if (strpos($file, 'api/') === 0) {
    $file = substr($file, 4);
}

if ($file === '' || $file === 'index') {
    $file = 'index.php';
} else if (substr($file, -4) !== '.php' && !strpos($file, '.')) {
    $file = $file . '.php';
}

$filePath = dirname(__DIR__) . '/' . $file;

if (file_exists($filePath) && is_file($filePath)) {
    chdir(dirname($filePath));
    require $filePath;
} else {
    http_response_code(404);
    echo "404 Not Found: " . htmlspecialchars($file);
}
