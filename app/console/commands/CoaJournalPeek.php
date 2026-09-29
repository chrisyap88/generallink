<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CoaJournalPeek extends Command
{
    protected $signature = 'coa:journal-peek';
    protected $description = 'One-off: show the 4 journal_lines rows and their parent journal_entries';

    public function handle()
    {
        $lines = DB::table('cbe_journal_lines')->get();
        foreach ($lines as $l) {
            $this->line(json_encode($l));
        }
        $this->line('---entries---');
        $jids = $lines->pluck('journal_id')->unique();
        $entries = DB::table('cbe_journal_entries')->whereIn('journal_id', $jids)->get();
        foreach ($entries as $e) {
            $this->line(json_encode($e));
        }
        return 0;
    }
}
