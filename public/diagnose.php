<?php
header('Content-Type: text/plain');

echo "=== GeneralLink diagnostic (delete this file when done) ===\n\n";

echo "PHP SAPI: " . php_sapi_name() . "\n";
echo "PHP version: " . phpversion() . "\n";
echo "php.ini loaded: " . php_ini_loaded_file() . "\n";
echo "__DIR__ (this script's real folder): " . __DIR__ . "\n";
echo "Document root (Apache's view): " . ($_SERVER['DOCUMENT_ROOT'] ?? 'n/a') . "\n\n";

$file = __DIR__ . '/../resources/views/admin/network/by-tl.blade.php';
echo "=== by-tl.blade.php as seen by THIS request ===\n";
echo "Path checked: $file\n";
echo "Realpath: " . (realpath($file) ?: 'COULD NOT RESOLVE - file missing at this path!') . "\n";
if (file_exists($file)) {
    echo "Exists: yes\n";
    echo "Size: " . filesize($file) . " bytes\n";
    echo "Last modified: " . date('Y-m-d H:i:s', filemtime($file)) . "\n";
    echo "MD5: " . md5_file($file) . "\n";
    $lines = file($file);
    echo "Total lines: " . count($lines) . "\n";
    echo "Line 113 content: " . ($lines[112] ?? '(no line 113)');
} else {
    echo "Exists: NO -- Apache is not seeing the file at the path I expect!\n";
}

echo "\n=== OPcache status ===\n";
if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    echo "opcache_get_status() available: yes\n";
    echo "OPcache enabled right now: " . ($st ? 'YES' : 'no / disabled') . "\n";
    echo "opcache.enable (ini): " . ini_get('opcache.enable') . "\n";
    echo "opcache.validate_timestamps (ini): " . ini_get('opcache.validate_timestamps') . "\n";
    echo "opcache.revalidate_freq (ini): " . ini_get('opcache.revalidate_freq') . "\n";
    echo "opcache.file_cache (ini): " . ini_get('opcache.file_cache') . "\n";
} else {
    echo "OPcache is not compiled into this PHP at all.\n";
}

echo "\n=== Compiled Laravel view cache folder ===\n";
$compiledDir = __DIR__ . '/../storage/framework/views';
if (is_dir($compiledDir)) {
    $files = glob($compiledDir . '/*.php');
    echo "Files currently in storage/framework/views: " . count($files) . "\n";
    foreach ($files as $f) {
        echo basename($f) . "  (" . date('Y-m-d H:i:s', filemtime($f)) . ")\n";
    }
} else {
    echo "Folder not found at: $compiledDir\n";
}

if (isset($_GET['reset'])) {
    echo "\n=== Manual opcache_reset() requested ===\n";
    if (function_exists('opcache_reset')) {
        $ok = opcache_reset();
        echo $ok ? "opcache_reset() ran successfully.\n" : "opcache_reset() returned false.\n";
    } else {
        echo "opcache_reset() function does not exist.\n";
    }
}
