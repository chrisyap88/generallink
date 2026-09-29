<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\BaseDashboardController;
class DashboardController extends BaseDashboardController {
    public function index() {
        return view('dashboard.admin', $this->dashboardData());
    }
}
