Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "  GeneralLink Complete Setup Script" -ForegroundColor Cyan  
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host ""

$base = "C:\xampp\htdocs\generallink"
$migPath = "$base\database\migrations"
$seedPath = "$base\database\seeders"
$routePath = "$base\routes"

# Ensure directories exist
New-Item -ItemType Directory -Force -Path $migPath | Out-Null
New-Item -ItemType Directory -Force -Path $seedPath | Out-Null

Write-Host "Writing route file..." -ForegroundColor Yellow
$webContent = @"
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\DashboardController     as AdminDashboard;
use App\Http\Controllers\GL\DashboardController        as GLDashboard;
use App\Http\Controllers\TL\DashboardController        as TLDashboard;
use App\Http\Controllers\Introducer\DashboardController as IntroducerDashboard;
use App\Http\Controllers\Shared\BeneficiaryController;
use App\Http\Controllers\Shared\PointsController;
use App\Http\Controllers\Shared\CustomerController;
use App\Http\Controllers\Shared\PolicyController;
use App\Http\Controllers\Shared\ProfileController;
use App\Http\Controllers\Admin\MasterFileController;
use App\Http\Controllers\Admin\BatchRegistrationController;
use App\Http\Controllers\Admin\AgentManagementController;

// -------------------------------------------------------
// GUEST ONLY
// -------------------------------------------------------
Route::middleware('guest:agent')->group(function () {
    Route::get('/',        [AuthController::class, 'showLogin'])->name('auth.login');
    Route::get('/login',   [AuthController::class, 'showLogin'])->name('auth.login.get');
    Route::post('/login',  [AuthController::class, 'login'])->name('auth.login.post');
    Route::get('/verify-email/{token}', [AuthController::class, 'verifyEmail'])->name('auth.verify-email');
    Route::get('/verify-pending',       [AuthController::class, 'verifyPending'])->name('auth.verify-pending');
});

// -------------------------------------------------------
// ALL AUTHENTICATED ROLES
// -------------------------------------------------------
Route::middleware('auth:agent')->group(function () {

    // Auth helpers
    Route::get('/security-phrase',  [AuthController::class, 'showSecurityPhrase'])->name('auth.security-phrase');
    Route::post('/security-phrase', [AuthController::class, 'saveSecurityPhrase'])->name('auth.security-phrase.save');
    Route::post('/logout',          [AuthController::class, 'logout'])->name('auth.logout');

    // Profile
    Route::get('/profile',             [ProfileController::class, 'index'])->name('profile.index');
    Route::patch('/profile',           [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password',  [ProfileController::class, 'changePassword'])->name('profile.password');
    Route::get('/profile/qr',          [ProfileController::class, 'generateQr'])->name('profile.qr');

    // Beneficiary
    Route::prefix('beneficiary')->name('beneficiary.')->group(function () {
        Route::get('/',        [BeneficiaryController::class, 'index'])->name('index');
        Route::post('/',       [BeneficiaryController::class, 'store'])->name('store');
        Route::put('/{id}',    [BeneficiaryController::class, 'update'])->name('update');
        Route::delete('/{id}', [BeneficiaryController::class, 'destroy'])->name('destroy');
    });

    // Points & Commission
    Route::prefix('points')->name('points.')->group(function () {
        Route::get('/',                     [PointsController::class, 'index'])->name('index');
        Route::post('/transfer-points',     [PointsController::class, 'transferPoints'])->name('transfer-points');
        Route::post('/transfer-commission', [PointsController::class, 'transferCommission'])->name('transfer-commission');
        Route::post('/submit-purchase',     [PointsController::class, 'submitPurchase'])->name('submit-purchase');
    });

    // Customers
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/',        [CustomerController::class, 'index'])->name('index');
        Route::get('/create',  [CustomerController::class, 'create'])->name('create');
        Route::post('/',       [CustomerController::class, 'store'])->name('store');
        Route::get('/{id}',    [CustomerController::class, 'show'])->name('show');
        Route::patch('/{id}',  [CustomerController::class, 'update'])->name('update');
    });

    // Policies
    Route::prefix('policies')->name('policies.')->group(function () {
        Route::get('/',          [PolicyController::class, 'index'])->name('index');
        Route::post('/',         [PolicyController::class, 'store'])->name('store');
        Route::get('/{id}',      [PolicyController::class, 'show'])->name('show');
        Route::patch('/{id}/cancel', [PolicyController::class, 'cancel'])->name('cancel');
    });
});

// -------------------------------------------------------
// ADMIN
// -------------------------------------------------------
Route::middleware(['auth:agent', 'role:ADMIN'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

    // Agent management
    Route::prefix('agents')->name('agents.')->group(function () {
        Route::get('/',               [AgentManagementController::class, 'index'])->name('index');
        Route::get('/{id}',           [AgentManagementController::class, 'show'])->name('show');
        Route::patch('/{id}/status',  [AgentManagementController::class, 'updateStatus'])->name('status');
    });

    // Point purchases
    Route::prefix('points')->name('points.')->group(function () {
        Route::get('/',              [PointsController::class, 'adminPurchases'])->name('index');
        Route::post('/{id}/approve', [PointsController::class, 'approvePurchase'])->name('approve');
        Route::post('/{id}/reject',  [PointsController::class, 'rejectPurchase'])->name('reject');
    });

    // Master File
    Route::prefix('masterfile')->name('masterfile.')->group(function () {
        Route::get('/',                      [MasterFileController::class, 'vendors'])->name('index');
        Route::get('/vendors',               [MasterFileController::class, 'vendors'])->name('vendors');
        Route::post('/vendors',              [MasterFileController::class, 'storeVendor'])->name('vendor.store');
        Route::patch('/vendors/{id}/toggle', [MasterFileController::class, 'toggleVendor'])->name('vendor.toggle');
        Route::get('/products',               [MasterFileController::class, 'products'])->name('products');
        Route::post('/products',              [MasterFileController::class, 'storeProduct'])->name('product.store');
        Route::patch('/products/{id}/toggle', [MasterFileController::class, 'toggleProduct'])->name('product.toggle');
        Route::get('/commissions',               [MasterFileController::class, 'commissionStructures'])->name('commissions');
        Route::post('/commissions',              [MasterFileController::class, 'storeCommissionStructure'])->name('commission.store');
        Route::patch('/commissions/{id}/toggle', [MasterFileController::class, 'toggleCommissionStructure'])->name('commission.toggle');
        Route::get('/groups',  [MasterFileController::class, 'groups'])->name('groups');
        Route::post('/groups', [MasterFileController::class, 'storeGroup'])->name('group.store');
        Route::get('/rates',   [MasterFileController::class, 'rewardRates'])->name('rates');
        Route::post('/rates',  [MasterFileController::class, 'storeRewardRate'])->name('rate.store');
    });

    // Batch Registration
    Route::prefix('batch')->name('batch.')->group(function () {
        Route::get('/',                           [BatchRegistrationController::class, 'index'])->name('index');
        Route::post('/upload',                    [BatchRegistrationController::class, 'upload'])->name('upload');
        Route::get('/template',                   [BatchRegistrationController::class, 'downloadTemplate'])->name('template');
        Route::get('/{batchId}',                  [BatchRegistrationController::class, 'show'])->name('show');
        Route::post('/{batchId}/validate',        [BatchRegistrationController::class, 'validate'])->name('validate');
        Route::post('/{batchId}/commit',          [BatchRegistrationController::class, 'commit'])->name('commit');
        Route::delete('/{batchId}/purge',         [BatchRegistrationController::class, 'purge'])->name('purge');
        Route::get('/{batchId}/rejection-report', [BatchRegistrationController::class, 'rejectionReport'])->name('rejection-report');
        Route::patch('/record/{recordId}',        [BatchRegistrationController::class, 'updateRecord'])->name('record.update');
    });
});

// -------------------------------------------------------
// GROUP LEADER
// -------------------------------------------------------
Route::middleware(['auth:agent', 'role:GROUP_LEADER'])->prefix('gl')->name('gl.')->group(function () {
    Route::get('/dashboard', [GLDashboard::class, 'index'])->name('dashboard');
});

// -------------------------------------------------------
// TEAM LEADER
// -------------------------------------------------------
Route::middleware(['auth:agent', 'role:TEAM_LEADER'])->prefix('tl')->name('tl.')->group(function () {
    Route::get('/dashboard', [TLDashboard::class, 'index'])->name('dashboard');
});

// -------------------------------------------------------
// INTRODUCER
// -------------------------------------------------------
Route::middleware(['auth:agent', 'role:INTRODUCER'])->prefix('introducer')->name('introducer.')->group(function () {
    Route::get('/dashboard', [IntroducerDashboard::class, 'index'])->name('dashboard');
});
"@
Set-Content -Path "$routePath\web.php" -Value $webContent -Encoding UTF8
Write-Host "Routes file written OK" -ForegroundColor Green
Write-Host ""
Write-Host "Writing migration files..." -ForegroundColor Yellow

$content = @"
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            // Primary key
            $table->uuid('agent_id')->primary();

            // Hierarchical member code (e.g. C0001-0-1-2) â€” immutable once set
            $table->string('member_code', 100)->unique()->nullable();

            // Human-readable agent code (e.g. GL-00001)
            $table->string('agent_code', 20)->unique()->nullable();

            // Identity
            $table->string('full_name', 200);
            $table->string('email', 200)->unique();
            $table->string('password_hash', 255);
            $table->text('nric_encrypted');           // AES-256 encrypted
            $table->string('phone', 20);

            // Role & status
            $table->enum('role', [
                'INTRODUCER',
                'TEAM_LEADER',
                'GROUP_LEADER',
                'ADMIN',
            ]);
            $table->enum('status', [
                'ACTIVE',
                'INACTIVE',
                'TERMINATED',
                'RISK_DEBT',
                'RESIGNED',
                'DECEASED',
            ])->default('ACTIVE');

            // Hierarchy
            $table->uuid('parent_id')->nullable();           // Direct sponsor/upline
            $table->string('hierarchy_path', 1000)->default('/'); // /GL_ID/TL_ID/I_ID/
            $table->uuid('group_id')->nullable();            // Root GL of this agent's tree

            // Tier restriction (Module 2A)
            $table->tinyInteger('recruitable_tier_depth')->default(0);
            $table->boolean('recruitment_blocked')->default(false);

            // QR onboarding
            $table->string('qr_code_token', 100)->unique()->nullable();

            // Bank details (for commission payout)
            $table->string('bank_name', 100)->nullable();
            $table->text('bank_account_encrypted')->nullable(); // AES-256 encrypted

            // Admin bank details (only used when role = ADMIN)
            $table->string('admin_bank_name', 100)->nullable();
            $table->text('admin_bank_account_encrypted')->nullable();

            // Commission wallet
            $table->decimal('commission_balance', 15, 4)->default(0);

            // Email verification
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_token', 100)->nullable();

            // Security phrase (first-time login)
            $table->string('security_phrase', 255)->nullable();
            $table->boolean('security_phrase_set')->default(false);

            // Failed login tracking
            $table->tinyInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();

            // Soft delete
            $table->boolean('is_deleted')->default(false);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps(); // created_at, updated_at

            // Indexes
            $table->index('parent_id');
            $table->index('group_id');
            $table->index('role');
            $table->index('status');
            $table->index('hierarchy_path');
            $table->index('member_code');
        });

        // Self-referencing FK for parent_id
        Schema::table('agents', function (Blueprint $table) {
            $table->foreign('parent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });
        Schema::dropIfExists('agents');
    }
};
"@
Set-Content -Path "$migPath‚6_01_01_000001_create_agents_table.php" -Value $content -Encoding UTF8
Write-Host "  Created: 2026_01_01_000001_create_agents_table.php" -ForegroundColor Cyan

$content = @"
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->uuid('group_id')->primary();

            // Group identity
            $table->string('group_name', 200);                    // GL full name
            $table->string('group_code', 20)->unique();           // e.g. C0001
            $table->string('group_email', 200);

            // Member code formatting (Module 2B)
            $table->char('separator_char', 1)->default('-');      // '-', '.', or '_'
            $table->string('root_member_suffix', 10)->default('0');

            // Status
            $table->boolean('is_active')->default(true);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index('group_code');
            $table->index('is_active');
        });

        // Add FK from groups to agents (created_by)
        Schema::table('groups', function (Blueprint $table) {
            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
"@
Set-Content -Path "$migPath‚6_01_01_000002_create_groups_table.php" -Value $content -Encoding UTF8
Write-Host "  Created: 2026_01_01_000002_create_groups_table.php" -ForegroundColor Cyan

$content = @"
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tier_recruitment_config', function (Blueprint $table) {
            $table->uuid('config_id')->primary();

            // NULL = global rule; populated for group-specific override
            $table->uuid('group_id')->nullable();

            // Maximum recruitable tier depth for Introducers (default 2)
            $table->tinyInteger('max_tier_limit')->default(2);

            $table->boolean('is_active')->default(true);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index('group_id');
            $table->index('is_active');

            $table->foreign('group_id')
                  ->references('group_id')
                  ->on('groups')
                  ->nullOnDelete();

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tier_recruitment_config');
    }
};
"@
Set-Content -Path "$migPath‚6_01_01_000003_create_tier_recruitment_config_table.php" -Value $content -Encoding UTF8
Write-Host "  Created: 2026_01_01_000003_create_tier_recruitment_config_table.php" -ForegroundColor Cyan

$content = @"
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->uuid('vendor_id')->primary();

            $table->string('vendor_name', 200);
            $table->string('vendor_code', 20)->unique();     // Short code e.g. ALZ
            $table->string('vendor_email', 200)->nullable();
            $table->string('vendor_phone', 20)->nullable();
            $table->text('vendor_address')->nullable();
            $table->string('pic_name', 200)->nullable();     // Person in charge

            $table->boolean('is_active')->default(true);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('is_active');

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
"@
Set-Content -Path "$migPath‚6_01_01_000004_create_vendors_table.php" -Value $content -Encoding UTF8
Write-Host "  Created: 2026_01_01_000004_create_vendors_table.php" -ForegroundColor Cyan

$content = @"
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('product_id')->primary();

            $table->uuid('vendor_id');
            $table->string('product_name', 200);
            $table->string('product_code', 20)->unique();
            $table->enum('product_type', [
                'MOTOR',
                'PERSONAL_ACCIDENT',
                'FIRE',
                'OTHER',
            ]);
            $table->text('description')->nullable();

            // Campaign support
            $table->date('campaign_start')->nullable();
            $table->date('campaign_end')->nullable();

            $table->boolean('is_active')->default(true);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('vendor_id');
            $table->index('product_type');
            $table->index('is_active');

            $table->foreign('vendor_id')
                  ->references('vendor_id')
                  ->on('vendors')
                  ->cascadeOnDelete();

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
"@
Set-Content -Path "$migPath‚6_01_01_000005_create_products_table.php" -Value $content -Encoding UTF8
Write-Host "  Created: 2026_01_01_000005_create_products_table.php" -ForegroundColor Cyan

$content = @"
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commission structure â€” fully data-driven per vendor + product.
     *
     * total_commission_pct  = gross pool % of premium or sum insured
     * introducer_pct        = Introducer's share of the pool (%)
     * team_leader_pct       = Team Leader's share of the pool (%)
     * group_leader_pct      = Group Leader's share of the pool (%)
     *
     * RULE: introducer_pct + team_leader_pct + group_leader_pct = 100.0000
     * Enforced at application layer before save.
     */
    public function up(): void
    {
        Schema::create('commission_structures', function (Blueprint $table) {
            $table->uuid('structure_id')->primary();

            $table->uuid('vendor_id');
            $table->uuid('product_id');

            // Whether pool is % of premium paid or % of sum insured
            $table->enum('commission_basis', [
                'PREMIUM_PCT',
                'SUM_INSURED_PCT',
            ])->default('PREMIUM_PCT');

            // Gross commission pool as % of the basis amount
            // e.g. 10.0000 for Motor, 25.0000 for PA, 15.0000 for Fire
            $table->decimal('total_commission_pct', 10, 4);

            // Role entitlement splits â€” must sum to 100.0000
            $table->decimal('introducer_pct', 10, 4)->default(0);
            $table->decimal('team_leader_pct', 10, 4)->default(0);
            $table->decimal('group_leader_pct', 10, 4)->default(0);

            // Time-bound campaign support
            $table->date('valid_from');
            $table->date('valid_to')->nullable();   // NULL = no expiry

            $table->boolean('is_active')->default(true);

            // Audit trail
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'product_id', 'is_active']);
            $table->index('valid_from');

            $table->foreign('vendor_id')
                  ->references('vendor_id')
                  ->on('vendors')
                  ->cascadeOnDelete();

            $table->foreign('product_id')
                  ->references('product_id')
                  ->on('products')
                  ->cascadeOnDelete();

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_structures');
    }
};
"@
Set-Content -Path "$migPath‚6_01_01_000006_create_commission_structures_table.php" -Value $content -Encoding UTF8
Write-Host "  Created: 2026_01_01_000006_create_commission_structures_table.php" -ForegroundColor Cyan

$content = @"
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('customer_id')->primary();

            // NRIC is unique â€” prevents duplicate customer records
            $table->text('nric_encrypted')->unique();   // AES-256; index on hash
            $table->string('nric_hash', 64)->unique();  // SHA-256 hash for uniqueness check

            $table->string('full_name', 200);
            $table->string('email', 200)->nullable();
            $table->string('phone', 20);
            $table->text('address')->nullable();
            $table->string('postcode', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();

            // Ownership â€” immutable after first policy submission
            $table->uuid('owned_by_agent_id');

            $table->boolean('is_deleted')->default(false);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('owned_by_agent_id');
            $table->index('nric_hash');

            $table->foreign('owned_by_agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
"@
Set-Content -Path "$migPath‚6_01_01_000007_create_customers_table.php" -Value $content -Encoding UTF8
Write-Host "  Created: 2026_01_01_000007_create_customers_table.php" -ForegroundColor Cyan

$content = @"
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_transactions', function (Blueprint $table) {
            $table->uuid('policy_id')->primary();

            // Core fields common to all product types
            $table->string('policy_number', 100)->unique();
            $table->uuid('vendor_id');
            $table->uuid('product_id');
            $table->uuid('customer_id');
            $table->uuid('agent_id');                    // Agent who submitted

            $table->decimal('premium_amount', 15, 4);
            $table->decimal('sum_insured', 15, 4)->nullable();

            $table->date('coverage_start');
            $table->date('coverage_end');
            $table->date('renewal_date')->nullable();

            $table->enum('status', [
                'DRAFT',
                'SUBMITTED',
                'ACTIVE',
                'PENDING_RENEWAL',
                'RENEWED',
                'LAPSED',
                'CANCELLED',
            ])->default('DRAFT');

            // Upload tracking
            $table->uuid('upload_batch_id')->nullable();     // Links to batch upload
            $table->uuid('template_id')->nullable();         // Field mapping template used

            // Version control for amendments
            $table->unsignedInteger('version')->default(1);
            $table->uuid('previous_version_id')->nullable(); // Points to prior version

            $table->boolean('is_deleted')->default(false);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('agent_id');
            $table->index('customer_id');
            $table->index('vendor_id');
            $table->index('product_id');
            $table->index('status');
            $table->index('renewal_date');
            $table->index('upload_batch_id');

            $table->foreign('vendor_id')
                  ->references('vendor_id')
                  ->on('vendors')
                  ->restrictOnDelete();

            $table->foreign('product_id')
                  ->references('product_id')
                  ->on('products')
                  ->restrictOnDelete();

            $table->foreign('customer_id')
                  ->references('customer_id')
                  ->on('customers')
                  ->restrictOnDelete();

            $table->foreign('agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->restrictOnDelete();
        });

        // EAV table for product-specific fields (vehicle reg, property address, etc.)
        Schema::create('sales_transaction_attributes', function (Blueprint $table) {
            $table->uuid('attr_id')->primary();
            $table->uuid('policy_id');
            $table->uuid('product_id');
            $table->string('attribute_name', 200);
            $table->text('attribute_value')->nullable();
            $table->timestamps();

            $table->index(['policy_id', 'attribute_name']);

            $table->foreign('policy_id')
                  ->references('policy_id')
                  ->on('sales_transactions')
                  ->cascadeOnDelete();

            $table->foreign('product_id')
                  ->references('product_id')
                  ->on('products')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_transaction_attributes');
        Schema::dropIfExists('sales_transactions');
    }
};
"@
Set-Content -Path "$migPath‚6_01_01_000008_create_sales_transactions_table.php" -Value $content -Encoding UTF8
Write-Host "  Created: 2026_01_01_000008_create_sales_transactions_table.php" -ForegroundColor Cyan

$content = @"
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_transactions', function (Blueprint $table) {
            $table->uuid('txn_id')->primary();

            $table->uuid('policy_id');
            $table->uuid('agent_id');                        // Who receives this commission
            $table->uuid('structure_id');                    // commission_structures record used

            $table->enum('role_at_transaction', [            // Agent's role at time of sale
                'INTRODUCER',
                'TEAM_LEADER',
                'GROUP_LEADER',
            ]);

            // Commission amounts â€” all derived from structure, never hard-coded
            $table->decimal('policy_premium', 15, 4);        // Snapshot of premium
            $table->decimal('total_pool_amount', 15, 4);     // Gross pool in RM
            $table->decimal('entitlement_pct', 10, 4);       // This agent's % of pool
            $table->decimal('commission_amount', 15, 4);     // RM amount credited

            // Breakage tracking
            $table->boolean('is_breakage')->default(false);  // True if credited to SYSTEM account
            $table->text('redistribution_reason')->nullable(); // Why tier was absent/skipped

            // Reward points (Module 10)
            $table->uuid('reward_points_rate_id')->nullable();
            $table->decimal('reward_points_earned', 15, 4)->default(0);

            $table->enum('status', [
                'PENDING',
                'CONFIRMED',
                'REVERSED',
            ])->default('PENDING');

            $table->uuid('reversed_by_txn_id')->nullable();  // Points to reversal txn

            // Audit â€” write-once
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index('agent_id');
            $table->index('policy_id');
            $table->index('status');
            $table->index('created_at');

            $table->foreign('policy_id')
                  ->references('policy_id')
                  ->on('sales_transactions')
                  ->restrictOnDelete();

            $table->foreign('agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->restrictOnDelete();

            $table->foreign('structure_id')
                  ->references('structure_id')
                  ->on('commission_structures')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_transactions');
    }
};
"@
Set-Content -Path "$migPath‚6_01_01_000009_create_commission_transactions_table.php" -Value $content -Encoding UTF8
Write-Host "  Created: 2026_01_01_000009_create_commission_transactions_table.php" -ForegroundColor Cyan

$content = @"
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rate table â€” configurable per vendor + product
        Schema::create('reward_points_rates', function (Blueprint $table) {
            $table->uuid('rate_id')->primary();

            // NULL = applies to all vendors / all products (cascading priority)
            $table->uuid('vendor_id')->nullable();
            $table->uuid('product_id')->nullable();

            // Points awarded per RM 1.00 of commission received
            $table->decimal('points_per_rm', 10, 4);

            $table->date('valid_from');
            $table->date('valid_to')->nullable();

            $table->boolean('is_active')->default(true);

            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'product_id', 'is_active']);

            $table->foreign('vendor_id')
                  ->references('vendor_id')
                  ->on('vendors')
                  ->nullOnDelete();

            $table->foreign('product_id')
                  ->references('product_id')
                  ->on('products')
                  ->nullOnDelete();

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });

        // Immutable ledger â€” write-once, corrections via reversal entries only
        Schema::create('reward_points_ledger', function (Blueprint $table) {
            $table->uuid('ledger_id')->primary();

            $table->uuid('agent_id');

            $table->enum('txn_type', [
                'EARNED',       // From commission
                'REDEEMED',     // Used for reward
                'CASHED_OUT',   // Converted to RM wallet
                'EXPIRED',      // Points expiry
                'REVERSED',     // Commission reversal clawback
                'ADJUSTMENT',   // Admin manual correction
                'PURCHASED',    // Bought by member via bank transfer
                'TRANSFERRED',  // Transferred to/from another agent
            ]);

            $table->decimal('points_in', 15, 4)->default(0);    // Credit
            $table->decimal('points_out', 15, 4)->default(0);   // Debit
            $table->decimal('running_balance', 15, 4);          // Cumulative balance

            // Source linkage
            $table->uuid('source_txn_id')->nullable();           // commission_transactions.txn_id
            $table->uuid('transfer_from_agent_id')->nullable();  // For TRANSFERRED entries
            $table->uuid('transfer_to_agent_id')->nullable();    // For TRANSFERRED entries
            $table->string('reference_no', 100)->nullable();     // Bank slip ref / redemption ref
            $table->text('notes')->nullable();

            // Write-once â€” no updated_at
            $table->uuid('created_by');
            $table->timestamp('created_at')->useCurrent();

            $table->index('agent_id');
            $table->index('txn_type');
            $table->index('created_at');
            $table->index('source_txn_id');

            $table->foreign('agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->restrictOnDelete();

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_points_ledger');
        Schema::dropIfExists('reward_points_rates');
    }
};
"@
Set-Content -Path "$migPath‚6_01_01_000010_create_reward_points_tables.php" -Value $content -Encoding UTF8
Write-Host "  Created: 2026_01_01_000010_create_reward_points_tables.php" -ForegroundColor Cyan

$content = @"
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->uuid('beneficiary_id')->primary();

            // The account this beneficiary belongs to
            $table->uuid('agent_id');

            // Beneficiary personal details (mirrors affiliate profile)
            $table->string('full_name', 200);
            $table->text('nric_encrypted');              // AES-256
            $table->string('relationship', 100);         // e.g. Spouse, Child, Parent
            $table->string('phone', 20)->nullable();
            $table->string('email', 200)->nullable();
            $table->text('address')->nullable();

            // Bank details for payout transfer on takeover
            $table->string('bank_name', 100)->nullable();
            $table->text('bank_account_encrypted')->nullable(); // AES-256

            // Priority order when multiple beneficiaries exist
            $table->tinyInteger('priority_order')->default(1);

            // Takeover tracking
            $table->boolean('takeover_triggered')->default(false);
            $table->timestamp('takeover_at')->nullable();
            $table->uuid('takeover_by')->nullable();          // Admin who triggered
            $table->text('takeover_notes')->nullable();

            $table->boolean('is_active')->default(true);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('agent_id');
            $table->index('takeover_triggered');

            $table->foreign('agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->cascadeOnDelete();

            $table->foreign('takeover_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
"@
Set-Content -Path "$migPath‚6_01_01_000011_create_beneficiaries_table.php" -Value $content -Encoding UTF8
Write-Host "  Created: 2026_01_01_000011_create_beneficiaries_table.php" -ForegroundColor Cyan

$content = @"
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_purchases', function (Blueprint $table) {
            $table->uuid('purchase_id')->primary();

            // Who is buying points
            $table->uuid('agent_id');

            // Amount paid and points to be credited
            $table->decimal('amount_paid_rm', 15, 4);
            $table->decimal('points_to_credit', 15, 4);

            // Bank-in slip evidence
            $table->string('bank_slip_path', 500)->nullable(); // Storage path
            $table->string('bank_slip_ref', 100)->nullable();  // Reference on slip
            $table->date('bank_in_date')->nullable();

            $table->enum('status', [
                'PENDING',    // Submitted, awaiting admin review
                'APPROVED',   // Admin approved, points credited
                'REJECTED',   // Admin rejected with reason
            ])->default('PENDING');

            // Admin action
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Links to the ledger entry created on approval
            $table->uuid('ledger_id')->nullable();

            $table->timestamps();

            $table->index('agent_id');
            $table->index('status');

            $table->foreign('agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->restrictOnDelete();

            $table->foreign('reviewed_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_purchases');
    }
};
"@
Set-Content -Path "$migPath‚6_01_01_000012_create_point_purchases_table.php" -Value $content -Encoding UTF8
Write-Host "  Created: 2026_01_01_000012_create_point_purchases_table.php" -ForegroundColor Cyan

$content = @"
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_upload_templates', function (Blueprint $table) {
            $table->uuid('template_id')->primary();

            $table->string('template_name', 200);
            $table->uuid('product_id');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'is_active']);

            $table->foreign('product_id')
                  ->references('product_id')
                  ->on('products')
                  ->restrictOnDelete();

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });

        Schema::create('template_field_mappings', function (Blueprint $table) {
            $table->uuid('mapping_id')->primary();

            $table->uuid('template_id');
            $table->string('source_column_name', 200);       // Column header from upload file
            $table->string('target_field_name', 200);         // System field name
            $table->enum('target_table', ['CORE', 'EXTENDED']);
            $table->boolean('is_mandatory')->default(false);
            $table->json('validation_rule')->nullable();       // {type, format, max_length}

            $table->timestamps();

            $table->index('template_id');

            $table->foreign('template_id')
                  ->references('template_id')
                  ->on('policy_upload_templates')
                  ->cascadeOnDelete();
        });

        // Upload batch tracking
        Schema::create('policy_upload_batches', function (Blueprint $table) {
            $table->uuid('batch_id')->primary();

            $table->uuid('template_id');
            $table->uuid('uploaded_by');
            $table->string('filename', 500);
            $table->unsignedInteger('rows_processed')->default(0);
            $table->unsignedInteger('rows_accepted')->default(0);
            $table->unsignedInteger('rows_rejected')->default(0);

            $table->enum('status', [
                'PROCESSING',
                'COMPLETED',
                'COMPLETED_WITH_ERRORS',
                'FAILED',
            ])->default('PROCESSING');

            $table->timestamps();

            $table->index('uploaded_by');
            $table->index('status');

            $table->foreign('template_id')
                  ->references('template_id')
                  ->on('policy_upload_templates')
                  ->restrictOnDelete();

            $table->foreign('uploaded_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_upload_batches');
        Schema::dropIfExists('template_field_mappings');
        Schema::dropIfExists('policy_upload_templates');
    }
};
"@
Set-Content -Path "$migPath‚6_01_01_000013_create_policy_upload_tables.php" -Value $content -Encoding UTF8
Write-Host "  Created: 2026_01_01_000013_create_policy_upload_tables.php" -ForegroundColor Cyan

$content = @"
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('log_id')->primary();

            $table->uuid('agent_id')->nullable();        // Who made the change
            $table->string('table_name', 100);           // Which table was changed
            $table->uuid('record_id');                   // Which record was changed
            $table->string('action', 20);                // CREATE, UPDATE, DELETE
            $table->json('before_value')->nullable();     // State before change
            $table->json('after_value')->nullable();      // State after change
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            // Write-once
            $table->timestamp('created_at')->useCurrent();

            $table->index('agent_id');
            $table->index('table_name');
            $table->index('record_id');
            $table->index('created_at');

            $table->foreign('agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });

        // Login event log
        Schema::create('login_logs', function (Blueprint $table) {
            $table->uuid('log_id')->primary();

            $table->uuid('agent_id')->nullable();
            $table->string('email_attempted', 200)->nullable();
            $table->boolean('success')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('agent_id');
            $table->index('created_at');
            $table->index('success');

            $table->foreign('agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });

        // Notification templates
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->uuid('template_id')->primary();

            $table->string('template_name', 200);
            $table->enum('channel', ['EMAIL', 'SMS', 'WHATSAPP', 'IN_APP']);
            $table->enum('event_type', [
                'RENEWAL_90_DAYS',
                'RENEWAL_60_DAYS',
                'RENEWAL_30_DAYS',
                'RENEWAL_7_DAYS',
                'WELCOME',
                'EMAIL_VERIFICATION',
                'PASSWORD_RESET',
                'COMMISSION_CREDITED',
                'POINTS_CREDITED',
                'RANK_PROMOTED',
                'RANK_DEMOTED',
            ]);
            $table->string('subject', 500)->nullable();   // Email subject
            $table->text('body_template');                // With {placeholders}

            $table->boolean('is_active')->default(true);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['channel', 'event_type', 'is_active']);

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('login_logs');
        Schema::dropIfExists('audit_logs');
    }
};
"@
Set-Content -Path "$migPath‚6_01_01_000014_create_audit_and_notification_tables.php" -Value $content -Encoding UTF8
Write-Host "  Created: 2026_01_01_000014_create_audit_and_notification_tables.php" -ForegroundColor Cyan

$content = @"
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_registration_staging', function (Blueprint $table) {
            $table->uuid('batch_id')->primary();
            $table->uuid('uploaded_by');
            $table->string('filename', 500);
            $table->enum('status', ['PENDING','VALIDATED','COMMITTED','FAILED'])->default('PENDING');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('committed_rows')->default(0);
            $table->timestamps();

            $table->index('uploaded_by');
            $table->index('status');

            $table->foreign('uploaded_by')->references('agent_id')->on('agents')->restrictOnDelete();
        });

        Schema::create('batch_registration_records', function (Blueprint $table) {
            $table->uuid('record_id')->primary();
            $table->uuid('batch_id');
            $table->unsignedInteger('row_number');
            $table->string('full_name', 200)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('nric', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('sponsor_code', 100)->nullable();
            $table->string('role', 30)->default('INTRODUCER');
            $table->enum('status', ['PENDING','VALID','INVALID','COMMITTED'])->default('PENDING');
            $table->json('validation_errors')->nullable();
            $table->uuid('committed_agent_id')->nullable();
            $table->timestamps();

            $table->index('batch_id');
            $table->index('status');

            $table->foreign('batch_id')->references('batch_id')->on('batch_registration_staging')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_registration_records');
        Schema::dropIfExists('batch_registration_staging');
    }
};
"@
Set-Content -Path "$migPath‚6_01_01_000015_create_batch_registration_tables.php" -Value $content -Encoding UTF8
Write-Host "  Created: 2026_01_01_000015_create_batch_registration_tables.php" -ForegroundColor Cyan

Write-Host ""
Write-Host "Writing seeder..." -ForegroundColor Yellow
$seederContent = @"
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------------------------------
        // 1. ADMIN AGENT
        // -------------------------------------------------------
        $adminId = Str::uuid()->toString();

        DB::table('agents')->insert([
            'agent_id'              => $adminId,
            'member_code'           => null,
            'agent_code'            => 'ADMIN-001',
            'full_name'             => 'GeneralLink Admin',
            'email'                 => 'admin@generallink.my',
            'password_hash'         => Hash::make('Admin@12345'),
            'nric_encrypted'        => encrypt('000000000000'),
            'phone'                 => '+60123456789',
            'role'                  => 'ADMIN',
            'status'                => 'ACTIVE',
            'parent_id'             => null,
            'hierarchy_path'        => '/',
            'group_id'              => null,
            'recruitable_tier_depth'=> 0,
            'recruitment_blocked'   => 0,
            'qr_code_token'         => Str::random(40),
            'admin_bank_name'       => 'Maybank',
            'admin_bank_account_encrypted' => encrypt('5621234567890'),
            'commission_balance'    => 0,
            'email_verified_at'     => now(),
            'security_phrase_set'   => true,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        // -------------------------------------------------------
        // 2. GROUP & GROUP LEADER
        // -------------------------------------------------------
        $groupId = Str::uuid()->toString();
        $glId    = Str::uuid()->toString();

        DB::table('agents')->insert([
            'agent_id'              => $glId,
            'member_code'           => null,   // assigned when group is set up
            'agent_code'            => 'GL-00001',
            'full_name'             => 'Chris Yap',
            'email'                 => 'chrisyap@generallink.my',
            'password_hash'         => Hash::make('Password@123'),
            'nric_encrypted'        => encrypt('800101015678'),
            'phone'                 => '+60112345678',
            'role'                  => 'GROUP_LEADER',
            'status'                => 'ACTIVE',
            'parent_id'             => null,
            'hierarchy_path'        => "/{$glId}/",
            'group_id'              => $groupId,
            'recruitable_tier_depth'=> 0,
            'recruitment_blocked'   => 0,
            'qr_code_token'         => Str::random(40),
            'bank_name'             => 'CIMB Bank',
            'bank_account_encrypted'=> encrypt('7081234567'),
            'commission_balance'    => 1250.00,
            'email_verified_at'     => now(),
            'security_phrase_set'   => true,
            'created_by'            => $adminId,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        DB::table('groups')->insert([
            'group_id'           => $groupId,
            'group_name'         => 'Chris Yap',
            'group_code'         => 'C0001',
            'group_email'        => 'chrisyap@generallink.my',
            'separator_char'     => '-',
            'root_member_suffix' => '0',
            'is_active'          => true,
            'created_by'         => $adminId,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // -------------------------------------------------------
        // 3. TEAM LEADER (under GL)
        // -------------------------------------------------------
        $tlId = Str::uuid()->toString();

        DB::table('agents')->insert([
            'agent_id'              => $tlId,
            'member_code'           => 'C0001-0',
            'agent_code'            => 'TL-00001',
            'full_name'             => 'Ahmad Razif',
            'email'                 => 'ahmad.razif@generallink.my',
            'password_hash'         => Hash::make('Password@123'),
            'nric_encrypted'        => encrypt('850215086543'),
            'phone'                 => '+60198765432',
            'role'                  => 'TEAM_LEADER',
            'status'                => 'ACTIVE',
            'parent_id'             => $glId,
            'hierarchy_path'        => "/{$glId}/{$tlId}/",
            'group_id'              => $groupId,
            'recruitable_tier_depth'=> 0,
            'recruitment_blocked'   => 0,
            'qr_code_token'         => Str::random(40),
            'bank_name'             => 'Public Bank',
            'bank_account_encrypted'=> encrypt('3141234567'),
            'commission_balance'    => 680.00,
            'email_verified_at'     => now(),
            'security_phrase_set'   => true,
            'created_by'            => $adminId,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        // -------------------------------------------------------
        // 4. INTRODUCERS (under TL)
        // -------------------------------------------------------
        $i1Id = Str::uuid()->toString();
        $i2Id = Str::uuid()->toString();

        $introducers = [
            [
                'agent_id'              => $i1Id,
                'member_code'           => 'C0001-0-1',
                'agent_code'            => 'I-00001',
                'full_name'             => 'Siti Nurhaliza',
                'email'                 => 'siti.nurhaliza@generallink.my',
                'phone'                 => '+60171234567',
                'nric_encrypted'        => encrypt('900303075432'),
                'commission_balance'    => 320.00,
                'recruitable_tier_depth'=> 1,
            ],
            [
                'agent_id'              => $i2Id,
                'member_code'           => 'C0001-0-2',
                'agent_code'            => 'I-00002',
                'full_name'             => 'Rajan Pillai',
                'email'                 => 'rajan.pillai@generallink.my',
                'phone'                 => '+60162345678',
                'nric_encrypted'        => encrypt('880912085321'),
                'commission_balance'    => 190.50,
                'recruitable_tier_depth'=> 1,
            ],
        ];

        foreach ($introducers as $intro) {
            DB::table('agents')->insert(array_merge($intro, [
                'password_hash'         => Hash::make('Password@123'),
                'role'                  => 'INTRODUCER',
                'status'                => 'ACTIVE',
                'parent_id'             => $tlId,
                'hierarchy_path'        => "/{$glId}/{$tlId}/{$intro['agent_id']}/",
                'group_id'              => $groupId,
                'recruitment_blocked'   => 0,
                'qr_code_token'         => Str::random(40),
                'bank_name'             => 'Maybank',
                'bank_account_encrypted'=> encrypt('1234567890'),
                'email_verified_at'     => now(),
                'security_phrase_set'   => true,
                'created_by'            => $adminId,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]));
        }

        // -------------------------------------------------------
        // 5. TIER RECRUITMENT CONFIG (global default = 2)
        // -------------------------------------------------------
        DB::table('tier_recruitment_config')->insert([
            'config_id'    => Str::uuid()->toString(),
            'group_id'     => null,        // Global rule
            'max_tier_limit'=> 2,
            'is_active'    => true,
            'created_by'   => $adminId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // -------------------------------------------------------
        // 6. VENDORS
        // -------------------------------------------------------
        $allianzId = Str::uuid()->toString();
        $aiaId     = Str::uuid()->toString();
        $zurichId  = Str::uuid()->toString();

        $vendors = [
            ['vendor_id' => $allianzId, 'vendor_name' => 'Allianz Malaysia', 'vendor_code' => 'ALZ'],
            ['vendor_id' => $aiaId,     'vendor_name' => 'AIA Malaysia',     'vendor_code' => 'AIA'],
            ['vendor_id' => $zurichId,  'vendor_name' => 'Zurich Insurance',  'vendor_code' => 'ZUR'],
        ];

        foreach ($vendors as $vendor) {
            DB::table('vendors')->insert(array_merge($vendor, [
                'is_active'  => true,
                'created_by' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // -------------------------------------------------------
        // 7. PRODUCTS
        // -------------------------------------------------------
        $motorId = Str::uuid()->toString();
        $paId    = Str::uuid()->toString();
        $fireId  = Str::uuid()->toString();

        $products = [
            ['product_id' => $motorId, 'vendor_id' => $allianzId, 'product_name' => 'Motor Comprehensive', 'product_code' => 'ALZ-MCOMP', 'product_type' => 'MOTOR'],
            ['product_id' => $paId,    'vendor_id' => $aiaId,     'product_name' => 'PA Plus',             'product_code' => 'AIA-PAPLUS','product_type' => 'PERSONAL_ACCIDENT'],
            ['product_id' => $fireId,  'vendor_id' => $zurichId,  'product_name' => 'Householder Fire',    'product_code' => 'ZUR-FIRE',  'product_type' => 'FIRE'],
        ];

        foreach ($products as $product) {
            DB::table('products')->insert(array_merge($product, [
                'is_active'  => true,
                'created_by' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // -------------------------------------------------------
        // 8. COMMISSION STRUCTURES (fully flexible â€” no hard-coded %)
        //    Examples only â€” admin can change these via Master File UI
        // -------------------------------------------------------
        $structures = [
            [
                // Motor: 10% of premium | I=50%, TL=25%, GL=25%
                'vendor_id'           => $allianzId,
                'product_id'          => $motorId,
                'commission_basis'    => 'PREMIUM_PCT',
                'total_commission_pct'=> 10.0000,
                'introducer_pct'      => 50.0000,
                'team_leader_pct'     => 25.0000,
                'group_leader_pct'    => 25.0000,
            ],
            [
                // PA: 25% of premium | I=60%, TL=25%, GL=15%
                'vendor_id'           => $aiaId,
                'product_id'          => $paId,
                'commission_basis'    => 'PREMIUM_PCT',
                'total_commission_pct'=> 25.0000,
                'introducer_pct'      => 60.0000,
                'team_leader_pct'     => 25.0000,
                'group_leader_pct'    => 15.0000,
            ],
            [
                // Fire: 15% of premium | I=55%, TL=25%, GL=20%
                'vendor_id'           => $zurichId,
                'product_id'          => $fireId,
                'commission_basis'    => 'PREMIUM_PCT',
                'total_commission_pct'=> 15.0000,
                'introducer_pct'      => 55.0000,
                'team_leader_pct'     => 25.0000,
                'group_leader_pct'    => 20.0000,
            ],
        ];

        foreach ($structures as $structure) {
            DB::table('commission_structures')->insert(array_merge($structure, [
                'structure_id' => Str::uuid()->toString(),
                'valid_from'   => '2026-01-01',
                'valid_to'     => null,
                'is_active'    => true,
                'created_by'   => $adminId,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]));
        }

        // -------------------------------------------------------
        // 9. REWARD POINTS RATES
        // -------------------------------------------------------
        DB::table('reward_points_rates')->insert([
            'rate_id'      => Str::uuid()->toString(),
            'vendor_id'    => null,     // Global rate â€” applies to all
            'product_id'   => null,
            'points_per_rm'=> 2.5000,   // 2.5 pts per RM1 commission
            'valid_from'   => '2026-01-01',
            'valid_to'     => null,
            'is_active'    => true,
            'created_by'   => $adminId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // -------------------------------------------------------
        // 10. NOTIFICATION TEMPLATES (sample)
        // -------------------------------------------------------
        $templates = [
            ['channel' => 'EMAIL', 'event_type' => 'EMAIL_VERIFICATION',  'subject' => 'Verify your GeneralLink account',      'body_template' => "Hi {name},\n\nPlease click the link below to verify your email:\n{verification_link}\n\nThis link expires in 24 hours."],
            ['channel' => 'EMAIL', 'event_type' => 'RENEWAL_30_DAYS',     'subject' => 'Your policy renews in 30 days',         'body_template' => "Hi {customer_name},\n\nYour policy {policy_number} is due for renewal on {renewal_date}.\n\nContact your agent {agent_name} at {agent_phone} to renew."],
            ['channel' => 'EMAIL', 'event_type' => 'COMMISSION_CREDITED',  'subject' => 'Commission credited to your account',   'body_template' => "Hi {agent_name},\n\nRM {amount} commission has been credited for policy {policy_number}.\n\nYour current balance: RM {balance}"],
            ['channel' => 'SMS',   'event_type' => 'RENEWAL_7_DAYS',      'subject' => null,                                    'body_template' => "GeneralLink: Policy {policy_number} renews in 7 days. Call {agent_phone} to renew now."],
        ];

        foreach ($templates as $tpl) {
            DB::table('notification_templates')->insert(array_merge($tpl, [
                'template_id' => Str::uuid()->toString(),
                'template_name' => $tpl['event_type'].'_'.$tpl['channel'],
                'is_active'   => true,
                'created_by'  => $adminId,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]));
        }

        $this->command->info('âœ… GeneralLink database seeded successfully.');
        $this->command->info('   Admin login: admin@generallink.my / Admin@12345');
        $this->command->info('   GL login:    chrisyap@generallink.my / Password@123');
        $this->command->info('   TL login:    ahmad.razif@generallink.my / Password@123');
    }
}
"@
Set-Content -Path "$seedPath\DatabaseSeeder.php" -Value $seederContent -Encoding UTF8
Write-Host "  Seeder written OK" -ForegroundColor Green

Write-Host ""
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "  All files written! Running setup..." -ForegroundColor Cyan
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host ""

Set-Location $base

Write-Host "Clearing config cache..." -ForegroundColor Yellow
& php artisan config:clear

Write-Host "Running fresh migrations..." -ForegroundColor Yellow
& php artisan migrate:fresh --seed --force

Write-Host ""
Write-Host "=====================================" -ForegroundColor Green
Write-Host "  SETUP COMPLETE!" -ForegroundColor Green
Write-Host "=====================================" -ForegroundColor Green
Write-Host ""
Write-Host "Starting server..." -ForegroundColor Yellow
Write-Host "Open browser: http://127.0.0.1:8000" -ForegroundColor Cyan
Write-Host "Admin login:  admin@generallink.my" -ForegroundColor Cyan
Write-Host "Password:     Admin@12345" -ForegroundColor Cyan
Write-Host ""
& php artisan serve
