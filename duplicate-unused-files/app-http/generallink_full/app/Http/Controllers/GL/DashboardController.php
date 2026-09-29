<?php
namespace App\Http\Controllers\GL;
use App\Http\Controllers\BaseDashboardController;
class DashboardController extends BaseDashboardController {
    public function index() {
        return view('dashboard.gl', $this->dashboardData());
    }
}
