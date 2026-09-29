<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EspoCrmService;

// NEW 28 Jul 2026 — EspoCRM integration (task #251). One simple screen so
// Chris can click a button and confirm GeneralLink can actually reach his
// EspoCRM install and that the API key works, before any real feature is
// wired on top of it.
class EspoCrmController extends Controller
{
    public function test(EspoCrmService $espoCrm)
    {
        $result = $espoCrm->testConnection();

        return view('admin.espocrm-test', ['result' => $result]);
    }
}
