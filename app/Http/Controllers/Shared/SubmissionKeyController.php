<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\SubmissionKeyService;
use Illuminate\Http\Request;

/**
 * NEW 18 Jul 2026 — lets any logged-in agent see their own personal
 * "submission key" (see SubmissionKeyService), so they know what to
 * paste into an email's subject line when submitting a document by
 * email instead of through the website upload form.
 */
class SubmissionKeyController extends Controller
{
    public function show(Request $request, SubmissionKeyService $keyService)
    {
        $agent = auth('agent')->user();
        $key = $keyService->generateFor($agent->agent_id);

        return view('shared.submission-key', compact('agent', 'key'));
    }
}
