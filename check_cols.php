<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "Checking sales_transactions columns...\n";
$cols = DB::select("SHOW COLUMNS FROM sales_transactions");
$required = [];
foreach ($cols as $col) {
    if ($col->Null === "NO" && $col->Default === null && $col->Extra !== "auto_increment") {
        $required[] = $col->Field;
    }
}
echo "Required columns: " . implode(", ", $required) . "\n";
