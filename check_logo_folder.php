<?php
$path = public_path('image');
echo "Checking path: " . $path . PHP_EOL;
echo "Folder exists: " . (is_dir($path) ? 'YES' : 'NO') . PHP_EOL;

if (is_dir($path)) {
    echo PHP_EOL . "--- Files found ---" . PHP_EOL;
    $files = scandir($path);
    foreach ($files as $f) {
        echo $f . PHP_EOL;
    }
}

echo PHP_EOL . 'Check complete.' . PHP_EOL;
