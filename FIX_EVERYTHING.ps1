Write-Host "Fixing GeneralLink - please wait..." -ForegroundColor Green

$base = "C:\xampp\htdocs\generallink"
Set-Location $base

# 1. Delete all conflicting old files
Write-Host "Step 1: Removing old conflicting files..." -ForegroundColor Yellow
Remove-Item "routes\auth.php" -Force -ErrorAction SilentlyContinue
Remove-Item "database\migrations\0001_01_01_000000_create_users_table.php" -Force -ErrorAction SilentlyContinue
Remove-Item "database\migrations\0001_01_01_000001_create_cache_table.php" -Force -ErrorAction SilentlyContinue
Remove-Item "database\migrations\0001_01_01_000002_create_jobs_table.php" -Force -ErrorAction SilentlyContinue

# 2. Fix bootstrap/app.php
Write-Host "Step 2: Fixing bootstrap/app.php..." -ForegroundColor Yellow
$bootstrap = '<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__."/../routes/web.php",
        commands: __DIR__."/../routes/console.php",
        health: "/up",
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            "role" => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();'
Set-Content -Path "$base\bootstrap\app.php" -Value $bootstrap -Encoding UTF8
Write-Host "  bootstrap/app.php fixed" -ForegroundColor Green

# 3. Fix .env - use file session driver
Write-Host "Step 3: Fixing .env session driver..." -ForegroundColor Yellow
$env = Get-Content "$base\.env" -Raw
$env = $env -replace 'SESSION_DRIVER=database', 'SESSION_DRIVER=file'
$env = $env -replace 'SESSION_DRIVER=cookie', 'SESSION_DRIVER=file'
Set-Content -Path "$base\.env" -Value $env -Encoding UTF8
Write-Host "  .env fixed" -ForegroundColor Green

# 4. Clear all caches
Write-Host "Step 4: Clearing all caches..." -ForegroundColor Yellow
& php artisan config:clear 2>$null
& php artisan route:clear 2>$null
& php artisan view:clear 2>$null
& php artisan cache:clear 2>$null
Write-Host "  All caches cleared" -ForegroundColor Green

# 5. Run migrations fresh
Write-Host "Step 5: Running fresh database setup..." -ForegroundColor Yellow
& php artisan migrate:fresh --seed --force

Write-Host ""
Write-Host "===============================" -ForegroundColor Cyan
Write-Host "  ALL FIXED! Starting server..." -ForegroundColor Cyan
Write-Host "===============================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Open browser: http://127.0.0.1:8000" -ForegroundColor Green
Write-Host "Email:    admin@generallink.my" -ForegroundColor Green
Write-Host "Password: Admin@12345" -ForegroundColor Green
Write-Host ""
& php artisan serve
