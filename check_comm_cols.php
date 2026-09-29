<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$cols = DB::select("SHOW COLUMNS FROM commission_transactions");
$required = [];
foreach ($cols as $c) {
    if ($c->Null === "NO" && $c->Default === null && $c->Extra !== "auto_increment") {
        $required[] = $c->Field;
    }
}
echo "Required: " . implode(", ", $required) . "\n";
