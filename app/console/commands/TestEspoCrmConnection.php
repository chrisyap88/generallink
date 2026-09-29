<?php

namespace App\Console\Commands;

use App\Services\EspoCrmService;
use Illuminate\Console\Command;

// NEW 29 Jul 2026 — EspoCRM integration (task #251). Replaces the
// one-off "EspoCRM Connection Test" screen/sidebar link Chris asked to
// have removed — this does the exact same check (hits EspoCRM's
// /App/user endpoint with the configured API key) but from the command
// line via TEST_ESPOCRM_CONNECTION.bat, so there's still a quick way to
// re-check the link (e.g. after changing the API key, or if reminders
// stop showing up in EspoCRM) without cluttering the app's menu.
class TestEspoCrmConnection extends Command
{
    protected $signature = 'espocrm:test';
    protected $description = 'Checks that GeneralLink can reach EspoCRM and that the API key is valid';

    public function handle(EspoCrmService $espoCrm): int
    {
        $result = $espoCrm->testConnection();

        if ($result['ok']) {
            $this->info('✅ ' . $result['message']);
            if (!empty($result['detail'])) {
                $detail = is_string($result['detail']) ? $result['detail'] : json_encode($result['detail']);
                $this->line('Logged in as: ' . $detail);
            }
            return self::SUCCESS;
        }

        $this->error('❌ ' . $result['message']);
        if (!empty($result['detail'])) {
            $detail = is_string($result['detail']) ? $result['detail'] : json_encode($result['detail']);
            $this->line($detail);
        }
        return self::FAILURE;
    }
}
