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

    public function getTicket(string $ticketId): array
    {
        return $this->freshdeskGet("/api/v2/tickets/{$ticketId}");
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

        $tickets = $this->freshdeskGet('/api/v2/tickets', $query);

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
        $ticket = $this->getTicket($ticketId);
        $data = $this->getMessages($ticketId);

        $conversation = Conversation::firstOrCreate(
            ['freshchat_id' => $ticketId],
            [
                'status' => (string) ($ticket['status'] ?? 'ticket'),
                'channel' => 'freshdesk',
            ]
        );

        $inserted = 0;
        $skipped = 0;

        // Save original client ticket message
        $originalMessageId = 'ticket_' . $ticketId . '_original';

        if (!Message::where('freshchat_id', $originalMessageId)->exists()) {
            Message::create([
                'freshchat_id' => $originalMessageId,
                'conversation_id' => $conversation->id,
                'contact_id' => null,
                'actor_type' => 'user',
                'content' => $this->cleanContent(
                    $ticket['description_text'] ?? $ticket['description'] ?? ''
                ),
                'message_type' => 'text',
                'is_ai' => false,
                'created_at' => $ticket['created_at'] ?? now(),
                'updated_at' => now(),
            ]);

            $inserted++;
        } else {
            $skipped++;
        }

        // Save ticket replies
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
                'content' => $this->cleanContent(
                    $msg['body_text'] ?? $msg['body'] ?? ''
                ),
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
    $page = 1;
    $total = 0;

    $export = [
        'exported_at' => now()->toDateTimeString(),
        'count' => 0,
        'conversations' => [],
    ];

    while (true) {
        // Get tickets directly from API (not DB)
        $tickets = $this->getTickets($startDate, $endDate, $page);

        if (count($tickets) === 0) {
            break;
        }

        foreach ($tickets as $ticket) {
            if (!isset($ticket['id'])) {
                continue;
            }

            $ticketId = (string) $ticket['id'];

            // Get original ticket
            $ticketData = $this->getTicket($ticketId);

            // Get replies
            $messagesData = $this->getMessages($ticketId);

            $messages = [];

            // Original client message
            $messages[] = [
                'role' => 'user',
                'content' => $this->cleanContent(
                    $ticketData['description_text'] ?? $ticketData['description'] ?? ''
                ),
            ];

            // Replies
            foreach ($messagesData['messages'] ?? [] as $msg) {
                $messages[] = [
                    'role' => !empty($msg['user_id']) ? 'assistant' : 'user',
                    'content' => $this->cleanContent(
                        $msg['body_text'] ?? $msg['body'] ?? ''
                    ),
                ];
            }

            $export['conversations'][] = [
                'conversation_id' => $ticketId,
                'messages' => $messages,
            ];

            $total++;

            // Stop if limit reached
            if ($total >= $limit) {
                break 2;
            }
        }

        $page++;

        // Prevent API spam
        usleep(500000);
    }

    $export['count'] = count($export['conversations']);

    $path = 'freshchat_exports/batch_' . now()->format('Ymd_His') . '.json';

    Storage::put($path, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return [
        'path' => $path,
        'count' => $export['count'],
        'data' => $export, // 👈 THIS makes it show in browser
    ];
}
    public function exportAll(int $limit = 10): array
{
    $tickets = array_slice($this->getTickets(null, null, 1), 0, $limit);

    $conversations = [];

    foreach ($tickets as $ticket) {
        if (!isset($ticket['id'])) {
            continue;
        }

        $ticketId = (string) $ticket['id'];

        $ticketData = $this->getTicket($ticketId);
        $messagesData = $this->getMessages($ticketId);

        $messages = [];

        $messages[] = [
            'role' => 'user',
            'content' => $this->cleanContent(
                $ticketData['description_text'] ?? $ticketData['description'] ?? ''
            ),
        ];

        foreach ($messagesData['messages'] ?? [] as $msg) {
            $messages[] = [
                'role' => !empty($msg['user_id']) ? 'assistant' : 'user',
                'content' => $this->cleanContent(
                    $msg['body_text'] ?? $msg['body'] ?? ''
                ),
            ];
        }

        $conversations[] = [
            'conversation_id' => $ticketId,
            'messages' => $messages,
        ];
    }

    $export = [
        'exported_at' => now()->toDateTimeString(),
        'count' => count($conversations),
        'conversations' => $conversations,
    ];

    $path = 'freshchat_exports/all_conversations_' . now()->format('Ymd_His') . '.json';

    Storage::put($path, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return [
        'path' => $path,
        'count' => count($conversations),
        'data' => $export,
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
        return [];
    }

    $response->throw();

    usleep(300000);

    return $response->json() ?? [];
}

    private function cleanContent(?string $content): string
    {
        if (!$content) {
            return '';
        }

        // Decode HTML entities like &nbsp;, &amp;, etc.
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Convert common HTML breaks into readable spacing.
        $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $content = preg_replace('/<\/p>/i', "\n", $content);
        $content = preg_replace('/<\/div>/i', "\n", $content);

        // Remove all remaining HTML tags.
        $content = strip_tags($content);

        // Remove long separator lines like ----- or =====.
        $content = preg_replace('/[-_=]{3,}/', '', $content);

        // Remove invisible/non-breaking spaces.
        $content = str_replace("\xc2\xa0", ' ', $content);

        // Normalize line breaks.
        $content = preg_replace("/\r\n|\r/", "\n", $content);

        // Remove too many blank lines.
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        // Clean extra spaces on each line.
        $lines = explode("\n", $content);
        $lines = array_map(function ($line) {
            return trim(preg_replace('/[ \t]+/', ' ', $line));
        }, $lines);

        // Remove empty lines at start/end.
        $content = trim(implode("\n", $lines));

        return $content;
    }

    public function exportBatchWithProgress(?string $startDate = null, ?string $endDate = null, int $limit = 20, string $jobId = null): array
{
    $tickets = array_slice($this->getTickets($startDate, $endDate, 1), 0, $limit);

    $total = count($tickets);
    $done = 0;

    $export = [
        'exported_at' => now()->toDateTimeString(),
        'count' => 0,
        'conversations' => [],
    ];

    foreach ($tickets as $ticket) {
        if (!isset($ticket['id'])) {
            continue;
        }

        $ticketId = (string) $ticket['id'];

        $ticketData = $this->getTicket($ticketId);
        $messagesData = $this->getMessages($ticketId);

        $messages = [];

        $messages[] = [
            'role' => 'user',
            'content' => $this->cleanContent(
                $ticketData['description_text'] ?? $ticketData['description'] ?? ''
            ),
        ];

        foreach ($messagesData['messages'] ?? [] as $msg) {
            $messages[] = [
                'role' => !empty($msg['user_id']) ? 'assistant' : 'user',
                'content' => $this->cleanContent(
                    $msg['body_text'] ?? $msg['body'] ?? ''
                ),
            ];
        }

        $export['conversations'][] = [
            'conversation_id' => $ticketId,
            'messages' => $messages,
        ];

        $done++;

        if ($jobId && $total > 0) {
            Storage::put("freshchat_exports/progress_{$jobId}.json", json_encode([
                'status' => 'running',
                'progress' => round(($done / $total) * 100),
                'message' => "Exporting ticket {$done} of {$total}",
                'path' => null,
            ]));
        }
    }

    $export['count'] = count($export['conversations']);

    $path = 'freshchat_exports/batch_' . now()->format('Ymd_His') . '.json';

    Storage::put($path, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if ($jobId) {
        Storage::put("freshchat_exports/progress_{$jobId}.json", json_encode([
            'status' => 'done',
            'progress' => 100,
            'message' => 'Export completed successfully.',
            'path' => $path,
        ]));
    }

    return [
        'path' => $path,
        'count' => $export['count'],
    ];
}
}