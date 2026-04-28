<?php

namespace App\Console\Commands;

use App\Services\FreshchatService;
use Illuminate\Console\Command;

class ExportFreshchat extends Command
{
    protected $signature = 'freshchat:export
        {--conversation= : Export one ticket/conversation}
        {--start= : Batch export start date}
        {--end= : Batch export end date}
        {--limit=100 : Batch size}';

    protected $description = 'Export Freshdesk conversations as AI-ready JSON';

    public function handle(FreshchatService $freshchatService): int
    {
        $conversationId = $this->option('conversation');

        if ($conversationId) {
            $path = $freshchatService->exportConversationFile($conversationId);

            if (!$path) {
                $this->error('Conversation not found. Save it first.');
                return self::FAILURE;
            }

            $this->info('Export created: storage/app/' . $path);
            return self::SUCCESS;
        }

        $result = $freshchatService->exportBatch(
            $this->option('start'),
            $this->option('end'),
            (int) $this->option('limit')
        );

        $this->info('Batch export created: storage/app/' . $result['path']);
        $this->line('Conversations exported: ' . $result['count']);

        return self::SUCCESS;
    }
}