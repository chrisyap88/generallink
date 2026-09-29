<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditService
{
    public static function logLogin(
        Request $request,
        ?string $agentId,
        bool    $success,
        ?string $failureReason = null
    ): void {
        DB::table('login_logs')->insert([
            'log_id'          => Str::uuid()->toString(),
            'agent_id'        => $agentId,
            'email_attempted' => $request->input('email'),
            'success'         => $success,
            'ip_address'      => $request->ip(),
            'user_agent'      => $request->userAgent(),
            'failure_reason'  => $failureReason,
            'created_at'      => now(),
        ]);
    }

    public static function logChange(
        string  $tableName,
        string  $recordId,
        string  $action,        // CREATE | UPDATE | DELETE
        mixed   $before = null,
        mixed   $after  = null,
        ?string $agentId = null,
        ?Request $request = null
    ): void {
        DB::table('audit_logs')->insert([
            'log_id'      => Str::uuid()->toString(),
            'agent_id'    => $agentId ?? auth('agent')->id(),
            'table_name'  => $tableName,
            'record_id'   => $recordId,
            'action'      => $action,
            'before_value'=> $before ? json_encode($before) : null,
            'after_value' => $after  ? json_encode($after)  : null,
            'ip_address'  => $request?->ip() ?? request()->ip(),
            'user_agent'  => $request?->userAgent() ?? request()->userAgent(),
            'created_at'  => now(),
        ]);
    }
}
