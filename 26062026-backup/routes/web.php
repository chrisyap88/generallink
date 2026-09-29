<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\ProfileController as AdminProfile;
use App\Http\Controllers\GL\DashboardController as GLDashboard;
use App\Http\Controllers\TL\DashboardController as TLDashboard;
use App\Http\Controllers\Introducer\DashboardController as IntroducerDashboard;
use App\Http\Controllers\Shared\ProfileController;
use App\Http\Controllers\Admin\MasterFileController;
use App\Http\Controllers\Admin\BatchRegistrationController;
use App\Http\Controllers\Admin\AgentManagementController;
use App\Http\Controllers\Admin\AgentProfileController;
use App\Http\Controllers\Admin\PendingAssignmentController;
use App\Http\Controllers\Admin\NetworkController;
use App\Http\Controllers\GL\TransactionController as GLTransaction;
use App\Http\Controllers\GL\CustomerController as GLCustomer;
use App\Http\Controllers\GL\CommissionController as GLCommission;
use App\Http\Controllers\GL\ProfileController as GLProfile;
use App\Http\Controllers\GL\NetworkController as GLNetwork;
use App\Http\Controllers\TL\ProfileController as TLProfile;
use App\Http\Controllers\Introducer\ProfileController as IntroducerProfile;

// GUEST ONLY
Route::middleware('guest:agent')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('auth.login');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login.post');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');

// GROUP LEADER (GL)
Route::prefix('gl')->name('gl.')->group(function () {
    Route::middleware('guest:agent')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login']);
    });
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::middleware(['auth:agent', 'role:GROUP_LEADER'])->group(function () {
        Route::get('dashboard', [GLDashboard::class, 'index'])->name('dashboard');
        Route::get('dashboard/metrics', [GLDashboard::class, 'metrics'])->name('dashboard.metrics');
        Route::get('dashboard/drilldown', [GLDashboard::class, 'drilldown'])->name('dashboard.drilldown');
        Route::get('dashboard/chart-drilldown', [GLDashboard::class, 'chartDrilldown'])->name('dashboard.chart.drilldown');
        Route::get('network', [GLNetwork::class, 'index'])->name('network');
        Route::get('network/intros/all', [\App\Http\Controllers\GL\IntroducerController::class, 'index'])->name('network.intros');
        Route::get('network/intros/{introId}/transactions', [GLNetwork::class, 'introTransactions'])->name('network.intro.transactions');
        Route::get('network/{tlId}', [GLNetwork::class, 'byTL'])->name('network.tl');
        Route::get('transactions', [GLTransaction::class, 'index'])->name('transactions');
        Route::get('transactions/{id}', [GLTransaction::class, 'show'])->name('transactions.show');
        Route::get('customers', [GLCustomer::class, 'index'])->name('customers.index');
        Route::get('customers/{id}', [GLCustomer::class, 'show'])->name('customers.show');
        Route::get('customers/{id}/edit', [GLCustomer::class, 'edit'])->name('customers.edit');
        Route::put('customers/{id}', [GLCustomer::class, 'update'])->name('customers.update');
        Route::get('commissions', [GLCommission::class, 'index'])->name('commissions.index');
        Route::get('rewards', [\App\Http\Controllers\GL\RewardController::class, 'index'])->name('rewards');
        Route::get('profile', [GLProfile::class, 'show'])->name('profile.show');
        Route::get('profile/edit', [GLProfile::class, 'edit'])->name('profile.edit');
        Route::put('profile', [GLProfile::class, 'update'])->name('profile.update');
        Route::get('profile/change-password', [GLProfile::class, 'changePasswordPage'])->name('profile.change-password');
        Route::put('profile/password', [GLProfile::class, 'changePassword'])->name('profile.password');
    });
});

// TEAM LEADER (TL)
Route::middleware(['auth:agent', 'role:TEAM_LEADER'])->prefix('tl')->name('tl.')->group(function () {
    Route::get('dashboard', [TLDashboard::class, 'index'])->name('dashboard');
    Route::get('introducers', [\App\Http\Controllers\TL\IntroducerController::class, 'index'])->name('introducers');
    Route::get('introducers/{id}/transactions', [\App\Http\Controllers\TL\IntroducerController::class, 'transactions'])->name('introducers.transactions');
    Route::get('transactions', [\App\Http\Controllers\TL\TransactionController::class, 'index'])->name('transactions');
    Route::get('transactions/{id}', [\App\Http\Controllers\TL\TransactionController::class, 'show'])->name('transactions.show');
    Route::get('rewards', [\App\Http\Controllers\TL\RewardController::class, 'index'])->name('rewards');
    Route::get('profile', [TLProfile::class, 'show'])->name('profile.show');
    Route::get('profile/edit', [TLProfile::class, 'edit'])->name('profile.edit');
    Route::put('profile', [TLProfile::class, 'update'])->name('profile.update');
    Route::get('profile/change-password', [TLProfile::class, 'changePasswordPage'])->name('profile.change-password');
    Route::put('profile/password', [TLProfile::class, 'changePassword'])->name('profile.password');
});

// INTRODUCER
Route::middleware(['auth:agent', 'role:INTRODUCER'])->prefix('introducer')->name('introducer.')->group(function () {
    Route::get('dashboard', [IntroducerDashboard::class, 'index'])->name('dashboard');
    Route::get('rewards', [\App\Http\Controllers\Introducer\RewardController::class, 'index'])->name('rewards');
    Route::get('recruits', [\App\Http\Controllers\Introducer\RecruitController::class, 'index'])->name('recruits');
    Route::get('recruits/{id}/transactions', [\App\Http\Controllers\Introducer\RecruitController::class, 'transactions'])->name('recruits.transactions');
    Route::get('profile', [IntroducerProfile::class, 'show'])->name('profile.show');
    Route::get('profile/edit', [IntroducerProfile::class, 'edit'])->name('profile.edit');
    Route::put('profile', [IntroducerProfile::class, 'update'])->name('profile.update');
    Route::get('profile/change-password', [IntroducerProfile::class, 'changePasswordPage'])->name('profile.change-password');
    Route::put('profile/password', [IntroducerProfile::class, 'changePassword'])->name('profile.password');
});

// ADMIN
Route::middleware(['auth:agent', 'role:ADMIN'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [AdminDashboard::class, 'index'])->name('dashboard');
    Route::get('dashboard/metrics', [AdminDashboard::class, 'metrics'])->name('dashboard.metrics');
    Route::get('dashboard/drilldown', [AdminDashboard::class, 'drilldown'])->name('dashboard.drilldown');
    Route::get('dashboard/chart-drilldown', [AdminDashboard::class, 'chartDrilldown'])->name('dashboard.chart.drilldown');

    // Agents
    Route::get('agents', [AgentManagementController::class, 'index'])->name('agents.index');
    Route::get('agents/pending', [PendingAssignmentController::class, 'index'])->name('agents.pending');
    Route::get('agents/{agentId}', [AgentProfileController::class, 'show'])->name('agents.show');
    Route::post('agents/{agentId}/assign-gl', [PendingAssignmentController::class, 'assign'])->name('agents.assign-gl');

    // Network — AJAX endpoints (must be before parameterised routes)
    Route::get('network/ajax/gls',       [NetworkController::class, 'ajaxGLs'])->name('network.ajax.gls');
    Route::get('network/ajax/tls',       [NetworkController::class, 'ajaxTLs'])->name('network.ajax.tls');
    Route::get('network/ajax/intros',    [NetworkController::class, 'ajaxIntros'])->name('network.ajax.intros');
    Route::get('network/ajax/children',  [NetworkController::class, 'ajaxChildren'])->name('network.ajax.children');
    Route::get('network/ajax/typeahead', [NetworkController::class, 'ajaxTypeahead'])->name('network.ajax.typeahead');

    // Network — Agent edit
    Route::get('network/agent/{id}/edit', [NetworkController::class, 'editAgent'])->name('network.agent.edit');
    Route::put('network/agent/{id}',      [NetworkController::class, 'updateAgent'])->name('network.agent.update');

    // Network — Excel export (must be before parameterised routes)
    Route::get('network/{glId}/export', [NetworkController::class, 'exportGL'])->name('network.gl.export');

    // Network — Drill down
    Route::get('network', [NetworkController::class, 'index'])->name('network');
    Route::get('network/{glId}', [NetworkController::class, 'byGL'])->name('network.gl');
    Route::get('network/{glId}/{tlId}', [NetworkController::class, 'byTL'])->name('network.tl');
    Route::get('network/{glId}/{tlId}/{introducerId}', [NetworkController::class, 'byIntroducer'])->name('network.introducer');

    // Profile
    Route::get('profile', [AdminProfile::class, 'show'])->name('profile.show');
    Route::get('profile/edit', [AdminProfile::class, 'edit'])->name('profile.edit');
    Route::put('profile', [AdminProfile::class, 'update'])->name('profile.update');
    Route::get('profile/change-password', [AdminProfile::class, 'changePasswordPage'])->name('profile.change-password');
    Route::put('profile/password', [AdminProfile::class, 'changePassword'])->name('profile.password');

    // Postcode lookup
    Route::get('postcode-lookup', function(\Illuminate\Http\Request $request) {
        $postcode = $request->get('postcode');
        $city     = $request->get('city');
        $partial  = $request->get('partial');
        if ($city) {
            $results = \Illuminate\Support\Facades\DB::table('malaysia_postcodes')
                ->where('city', 'like', '%'.$city.'%')
                ->orderBy('city')->limit(20)->get();
            return response()->json($results);
        }
        if ($partial) {
            $results = \Illuminate\Support\Facades\DB::table('malaysia_postcodes')
                ->where('postcode', 'like', $postcode.'%')
                ->orderBy('postcode')->limit(10)->get();
            return response()->json($results);
        }
        $result = \Illuminate\Support\Facades\DB::table('malaysia_postcodes')
            ->where('postcode', $postcode)->first();
        return response()->json($result ?: ['city' => '', 'state' => '']);
    })->name('postcode.lookup');

    // Vendors
    Route::get('vendors/search-edit', [\App\Http\Controllers\Admin\VendorController::class, 'searchEdit'])->name('vendors.search-edit');
    Route::get('vendors', [\App\Http\Controllers\Admin\VendorController::class, 'index'])->name('vendors.index');
    Route::post('vendors', [\App\Http\Controllers\Admin\VendorController::class, 'store'])->name('vendors.store');
    Route::put('vendors/{id}', [\App\Http\Controllers\Admin\VendorController::class, 'update'])->name('vendors.update');
    Route::post('vendors/{id}/branches', [\App\Http\Controllers\Admin\VendorController::class, 'storeBranch'])->name('vendors.branches.store');
    Route::put('vendors/branches/{id}', [\App\Http\Controllers\Admin\VendorController::class, 'updateBranch'])->name('vendors.branches.update');

    // Branches
    Route::get('branches/search-edit', [\App\Http\Controllers\Admin\BranchController::class, 'searchEdit'])->name('branches.search-edit');
    Route::get('branches', [\App\Http\Controllers\Admin\BranchController::class, 'index'])->name('branches.index');
    Route::post('branches', [\App\Http\Controllers\Admin\BranchController::class, 'store'])->name('branches.store');
    Route::put('branches/{id}', [\App\Http\Controllers\Admin\BranchController::class, 'update'])->name('branches.update');

    // Master File — Vendors (legacy)
    Route::get('masterfile/vendors', [MasterFileController::class, 'vendors'])->name('masterfile.vendors');
    Route::post('masterfile/vendors', [MasterFileController::class, 'storeVendor'])->name('masterfile.vendors.store');
    Route::patch('masterfile/vendors/{id}/toggle', [MasterFileController::class, 'toggleVendor'])->name('masterfile.vendor.toggle');

    // Master File — Products
    Route::get('masterfile/products', [MasterFileController::class, 'products'])->name('masterfile.products');
    Route::post('masterfile/products', [MasterFileController::class, 'storeProduct'])->name('masterfile.products.store');
    Route::put('masterfile/products/{id}', [MasterFileController::class, 'updateProduct'])->name('masterfile.products.update');
    Route::patch('masterfile/products/{id}/toggle', [MasterFileController::class, 'toggleProduct'])->name('masterfile.product.toggle');

    // Master File — Commission Structures
    Route::get('masterfile/commissions', [MasterFileController::class, 'commissionStructures'])->name('masterfile.commissions');
    Route::post('masterfile/commissions', [MasterFileController::class, 'storeCommissionStructure'])->name('masterfile.commissions.store');
    Route::patch('masterfile/commissions/{id}/toggle', [MasterFileController::class, 'toggleCommissionStructure'])->name('masterfile.commission.toggle');

    // Master File — Reward Rates
    Route::get('masterfile/reward-rates', [MasterFileController::class, 'rewardRates'])->name('masterfile.reward-rates');
    Route::post('masterfile/reward-rates', [MasterFileController::class, 'storeRewardRate'])->name('masterfile.reward-rates.store');

    // Batch
    Route::patch('record/{recordId}', [BatchRegistrationController::class, 'updateRecord'])->name('record.update');

    // Placeholder routes
    Route::get('transactions', function() { return redirect()->route('admin.dashboard'); })->name('transactions');
    Route::get('points', function() { return redirect()->route('admin.dashboard'); })->name('points.index');
});
