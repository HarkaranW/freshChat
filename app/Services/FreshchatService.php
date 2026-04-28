<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FreshchatService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('freshchat.base_url'), '/');
        $this->apiKey = config('freshchat.api_key');
    }

    public function getTickets(?string $startDate = null, ?string $endDate = null, int $page = 1): array
    {
        $query = [
            'page' => $page,
            'per_page' => 100,
            'order_by' => 'updated_at',
            'order_type' => 'asc',
        ];

        if ($startDate) {
            $query['updated_since'] = $startDate . 'T00:00:00Z';
        }

        $response = $this->freshdeskGet('/api/v2/tickets', $query);

        $tickets = $response;

        if ($endDate) {
            $tickets = array_filter($tickets, function ($ticket) use ($endDate) {
                return isset($ticket['updated_at']) && substr($ticket['updated_at'], 0, 10) <= $endDate;
            });
        }

        return array_values($tickets);
    }

    public function getMessages(string $ticketId): array
    {
        $messages = $this->freshdeskGet("/api/v2/tickets/{$ticketId}/conversations");

        return [
            'conversation_id' => $ticketId,
            'messages' => $messages,
        ];
    }

    public function saveConversation(string $ticketId): array
    {
        $data = $this->getMessages($ticketId);

        $conversation = Conversation::firstOrCreate(
            ['freshchat_id' => $ticketId],
            [
                'status' => 'ticket',
                'channel' => 'freshdesk',
            ]
        );

        $inserted = 0;
        $skipped = 0;

        foreach ($data['messages'] ?? [] as $msg) {
            $messageId = (string) ($msg['id'] ?? '');

            if ($messageId === '') {
                $skipped++;
                continue;
            }

            if (Message::where('freshchat_id', $messageId)->exists()) {
                $skipped++;
                continue;
            }

            $actorType = !empty($msg['user_id']) ? 'agent' : 'user';

            Message::create([
                'freshchat_id' => $messageId,
                'conversation_id' => $conversation->id,
                'contact_id' => null,
                'actor_type' => $actorType,
                'content' => $msg['body_text'] ?? strip_tags($msg['body'] ?? ''),
                'message_type' => 'text',
                'is_ai' => false,
                'created_at' => $msg['created_at'] ?? now(),
                'updated_at' => now(),
            ]);

            $inserted++;
        }

        return [
            'ticket_id' => $ticketId,
            'inserted' => $inserted,
            'skipped' => $skipped,
        ];
    }

    public function syncByPeriod(string $startDate, string $endDate): array
    {
        $page = 1;
        $totalTickets = 0;
        $totalInserted = 0;
        $totalSkipped = 0;
        $results = [];

        while (true) {
            $tickets = $this->getTickets($startDate, $endDate, $page);

            if (count($tickets) === 0) {
                break;
            }

            foreach ($tickets as $ticket) {
                if (!isset($ticket['id'])) {
                    continue;
                }

                $result = $this->saveConversation((string) $ticket['id']);

                $results[] = $result;
                $totalTickets++;
                $totalInserted += $result['inserted'];
                $totalSkipped += $result['skipped'];
            }

            if (count($tickets) < 100) {
                break;
            }

            $page++;
        }

        return [
            'start' => $startDate,
            'end' => $endDate,
            'tickets_synced' => $totalTickets,
            'messages_inserted' => $totalInserted,
            'duplicates_skipped' => $totalSkipped,
            'results' => $results,
        ];
    }

    public function databaseSnapshot(): array
    {
        return [
            'conversations' => Conversation::orderByDesc('created_at')->get(),
            'messages' => Message::orderBy('created_at')->get(),
        ];
    }

    public function exportConversationForAI(string $ticketId): ?array
    {
        $conversation = Conversation::where('freshchat_id', $ticketId)
            ->with(['messages' => fn ($query) => $query->orderBy('created_at')])
            ->first();

        if (!$conversation) {
            return null;
        }

        return [
            'conversation_id' => $ticketId,
            'messages' => $conversation->messages->map(function ($message) {
                return [
                    'role' => $message->actor_type === 'user' ? 'user' : 'assistant',
                    'content' => $message->content,
                ];
            })->values()->toArray(),
        ];
    }

    public function exportConversationFile(string $ticketId): ?string
    {
        $export = $this->exportConversationForAI($ticketId);

        if (!$export) {
            return null;
        }

        $path = "freshchat_exports/ticket_{$ticketId}.json";

        Storage::put($path, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    public function exportBatch(?string $startDate = null, ?string $endDate = null, int $limit = 100): array
    {
        $query = Conversation::query()->orderBy('created_at');

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $conversations = $query->limit($limit)->get();

        $batch = [
            'exported_at' => now()->toDateTimeString(),
            'count' => $conversations->count(),
            'conversations' => [],
        ];

        foreach ($conversations as $conversation) {
            $export = $this->exportConversationForAI($conversation->freshchat_id);

            if ($export) {
                $batch['conversations'][] = $export;
            }
        }

        $path = 'freshchat_exports/batch_' . now()->format('Ymd_His') . '.json';

        Storage::put($path, json_encode($batch, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [
            'path' => $path,
            'count' => count($batch['conversations']),
            'data' => $batch,
        ];
    }

    private function freshdeskGet(string $endpoint, array $query = []): array
{
    $url = $this->baseUrl . $endpoint;

    $response = Http::withHeaders([
        'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':X'),
        'Accept' => 'application/json',
    ])->get($url, $query);

    if ($response->status() === 429) {
        sleep(60);

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':X'),
            'Accept' => 'application/json',
        ])->get($url, $query);
    }

    $response->throw();

    // Small delay so we do not spam Freshdesk API
    usleep(500000); // 0.5 seconds

    return $response->json() ?? [];
}
}