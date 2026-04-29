<?php

namespace App\Http\Controllers;

use App\Services\FreshchatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FreshchatController extends Controller
{
    public function __construct(private FreshchatService $freshchatService)
    {
    }

    public function index()
    {
        $tickets = $this->freshchatService->getTickets();

        return view('freshchat.index', [
            'tickets' => $tickets,
        ]);
    }

    public function tickets()
    {
        return response()->json($this->freshchatService->getTickets());
    }

    public function testApi(Request $request)
    {
        $ticketId = $request->query('ticket_id');

        if (!$ticketId) {
            return redirect('/freshchat');
        }

        return response()->json($this->freshchatService->getMessages($ticketId));
    }

    public function save(Request $request)
    {
        $ticketId = $request->query('ticket_id');

        if (!$ticketId) {
            return redirect('/freshchat');
        }

        return response()->json($this->freshchatService->saveConversation($ticketId));
    }

    public function database()
    {
        return response()->json($this->freshchatService->databaseSnapshot());
    }

    public function export(Request $request)
    {
        $ticketId = $request->query('ticket_id');

        if (!$ticketId) {
            return redirect('/freshchat');
        }

        $export = $this->freshchatService->exportConversationForAI($ticketId);

        if (!$export) {
            return response()->json([
                'message' => 'Ticket not found in local database. Click Save first.',
            ], 404);
        }

        $path = $this->freshchatService->exportConversationFile($ticketId);

        return response()->json([
            'path' => $path,
            'data' => $export,
        ]);
    }

    public function sync(Request $request)
{
    $request->validate([
        'start' => ['required', 'date'],
        'end' => ['required', 'date'],
    ]);

    return response()->json(
        $this->freshchatService->syncByPeriod(
            $request->query('start'),
            $request->query('end')
        )
    );
}

public function exportBatch(Request $request)
{
    $result = $this->freshchatService->exportBatch(
        $request->query('start'),
        $request->query('end'),
        (int) $request->query('limit', 20)
    );

    return response()->download(storage_path('app/private/' . $result['path']));
}

public function exportAll(Request $request)
{
    $result = $this->freshchatService->exportAll(
        (int) $request->query('limit', 10)
    );

    return response()->download(storage_path('app/private/' . $result['path']));
}
}