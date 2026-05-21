<?php
// clean_urls.php - Script to remove .php from internal links and redirects

$directory = __DIR__ . '/..';
$files = glob($directory . '/*.php');

foreach ($files as $file) {
    if (basename($file) === 'clean_urls.php' || basename($file) === 'koneksi.php' || basename($file) === 'clean_urls.php') continue;
    
    $content = file_get_contents($file);
    $original = $content;

    // 1. href="filename.php" or href='filename.php' or href="filename.php?..."
    $content = preg_replace('/(href=["\'])([^"\']+?)\.php(\?|["\'])/i', '$1$2$3', $content);

    // 2. Location: filename.php
    $content = preg_replace('/(Location:\s+)([^?\s\n\r]+)\.php/i', '$1$2', $content);

    // 3. fetch('filename.php...')
    $content = preg_replace('/(fetch\s*\(\s*["\'`][^"\'`\s?]+?)\.php/i', '$1', $content);

    // 4. window.location.href = 'filename.php...' or backticks
    $content = preg_replace('/(window\.location\.href\s*=\s*["\'`][^"\'`\s?]+?)\.php/i', '$1$2', $content);
    
    // 5. onclick="window.location.href='filename.php...'"
    $content = preg_replace('/(location\.href\s*=\s*["\'`][^"\'`\s?]+?)\.php/i', '$1', $content);
    
    // 5. action="filename.php"
    $content = preg_replace('/(action=["\'])([^"\']+?)\.php/i', '$1$2', $content);

    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Updated links in " . basename($file) . "\n";
    }
}
?>
