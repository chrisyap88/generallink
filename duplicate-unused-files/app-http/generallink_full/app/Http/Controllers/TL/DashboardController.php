<?php
namespace App\Http\Controllers\TL;
use App\Http\Controllers\BaseDashboardController;
class DashboardController extends BaseDashboardController {
    public function index() {
        return view('dashboard.tl', $this->dashboardData());
    }
}
