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

        // Network
        Route::get('network', [GLNetwork::class, 'index'])->name('network');
        Route::get('network/{tlId}', [GLNetwork::class, 'byTL'])->name('network.tl');

        // Transactions
        Route::get('transactions', [GLTransaction::class, 'index'])->name('transactions');
        Route::get('transactions/{id}', [GLTransaction::class, 'show'])->name('transactions.show');

        // Customers
        Route::get('customers', [GLCustomer::class, 'index'])->name('customers.index');
        Route::get('customers/{id}', [GLCustomer::class, 'show'])->name('customers.show');
        Route::get('customers/{id}/edit', [GLCustomer::class, 'edit'])->name('customers.edit');
        Route::put('customers/{id}', [GLCustomer::class, 'update'])->name('customers.update');

        // Commissions
        Route::get('commissions', [GLCommission::class, 'index'])->name('commissions.index');

        // Reward Points
        Route::get('rewards', [\App\Http\Controllers\GL\RewardController::class, 'index'])->name('rewards');

        // Profile
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

    // Profile
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

    // Profile
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

    // Agents
    Route::get('agents', [AgentManagementController::class, 'index'])->name('agents.index');
    Route::get('agents/pending', [PendingAssignmentController::class, 'index'])->name('agents.pending');
    Route::get('agents/{agentId}', [AgentProfileController::class, 'show'])->name('agents.show');
    Route::post('agents/{agentId}/assign-gl', [PendingAssignmentController::class, 'assign'])->name('agents.assign-gl');

    // Network
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

    // Masterfile
    Route::patch('masterfile/commissions/{id}/toggle', [MasterFileController::class, 'toggleCommission'])->name('masterfile.commission.toggle');
    Route::patch('masterfile/products/{id}/toggle', [MasterFileController::class, 'toggleProduct'])->name('masterfile.product.toggle');
    Route::patch('masterfile/vendors/{id}/toggle', [MasterFileController::class, 'toggleVendor'])->name('masterfile.vendor.toggle');

    // Batch
    Route::patch('record/{recordId}', [BatchRegistrationController::class, 'updateRecord'])->name('record.update');

    // Placeholder routes
    Route::get('transactions', function() { return redirect()->route('admin.dashboard'); })->name('transactions');
    Route::get('points', function() { return redirect()->route('admin.dashboard'); })->name('points.index');
});
