<?php

namespace App\Console\Commands;

use App\Services\FreshchatService;
use Illuminate\Console\Command;

class SyncFreshchatOne extends Command
{
    protected $signature = 'freshchat:sync-one {conversation_id}';

    protected $description = 'Sync one Freshchat conversation by conversation ID';

    public function handle(FreshchatService $freshchatService): int
    {
        $conversationId = $this->argument('conversation_id');

        $result = $freshchatService->saveConversation($conversationId);

        $this->info('Conversation synced.');
        $this->line('Freshchat ID: ' . $result['conversation_freshchat_id']);
        $this->line('Inserted messages: ' . $result['inserted']);
        $this->line('Skipped duplicates: ' . $result['skipped']);

        return self::SUCCESS;
    }
}
