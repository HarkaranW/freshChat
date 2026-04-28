<?php

namespace App\Console\Commands;

use App\Services\FreshchatService;
use Illuminate\Console\Command;

class SyncFreshchat extends Command
{
    protected $signature = 'freshchat:sync {--start=} {--end=}';

    protected $description = 'Sync Freshdesk tickets by period';

    public function handle(FreshchatService $freshchatService): int
    {
        $start = $this->option('start');
        $end = $this->option('end');

        if (!$start || !$end) {
            $this->error('Use: php artisan freshchat:sync --start=YYYY-MM-DD --end=YYYY-MM-DD');
            return self::FAILURE;
        }

        $result = $freshchatService->syncByPeriod($start, $end);

        $this->info('Sync completed.');
        $this->line('Tickets synced: ' . $result['tickets_synced']);
        $this->line('Messages inserted: ' . $result['messages_inserted']);
        $this->line('Duplicates skipped: ' . $result['duplicates_skipped']);

        return self::SUCCESS;
    }
}