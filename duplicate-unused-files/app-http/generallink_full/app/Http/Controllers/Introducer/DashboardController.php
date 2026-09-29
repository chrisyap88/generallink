<?php
namespace App\Http\Controllers\Introducer;
use App\Http\Controllers\BaseDashboardController;
class DashboardController extends BaseDashboardController {
    public function index() {
        return view('dashboard.introducer', $this->dashboardData());
    }
}
