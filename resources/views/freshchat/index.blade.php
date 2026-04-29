<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Freshdesk Ticket Export</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f6f7f9;
            color: #172033;
            font-family: Arial, Helvetica, sans-serif;
        }

        .container {
            width: min(1180px, 94%);
            margin: 32px auto;
        }

        .header,
        .card,
        .stat,
        .form-box {
            background: #ffffff;
            border: 1px solid #e5e7eb;
        }

        .header {
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 30px;
            letter-spacing: -0.03em;
        }

        .header p {
            margin: 0;
            color: #64748b;
            line-height: 1.6;
            max-width: 760px;
        }

        .top-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat {
            border-radius: 14px;
            padding: 18px;
        }

        .stat-label {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
        }

        .card {
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 20px;
        }

        .card-title {
            margin: 0 0 6px;
            font-size: 20px;
            letter-spacing: -0.02em;
        }

        .card-subtitle {
            margin: 0 0 18px;
            color: #64748b;
            font-size: 14px;
        }

        .forms {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .form-box {
            border-radius: 14px;
            padding: 16px;
            background: #fafafa;
        }

        .form-box h3 {
            margin: 0 0 12px;
            font-size: 16px;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: end;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        label {
            color: #475569;
            font-size: 13px;
            font-weight: 700;
        }

        input {
            min-width: 150px;
            padding: 10px 11px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #ffffff;
            color: #172033;
        }

        input:focus {
            outline: 2px solid #bfdbfe;
            border-color: #2563eb;
        }

        .button,
        button {
            display: inline-block;
            border: 0;
            cursor: pointer;
            background: #2563eb;
            color: #ffffff;
            padding: 10px 13px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            line-height: 1;
        }

        .button:hover,
        button:hover {
            background: #1d4ed8;
        }

        .button.secondary {
            background: #334155;
        }

        .button.secondary:hover {
            background: #1e293b;
        }

        .button.success,
        button.success {
            background: #15803d;
        }

        .button.success:hover,
        button.success:hover {
            background: #166534;
        }

        .button.small {
            padding: 8px 10px;
            font-size: 13px;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        th,
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .ticket-id {
            color: #2563eb;
            font-weight: 700;
        }

        .subject {
            font-weight: 700;
            margin-bottom: 4px;
        }

        .muted {
            color: #64748b;
            font-size: 13px;
        }

        .badge {
            display: inline-block;
            background: #eef2ff;
            color: #3730a3;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 13px;
            font-weight: 700;
        }

        .row-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .empty {
            padding: 30px;
            text-align: center;
            color: #64748b;
        }

        .error {
            border-color: #fecdd3;
            background: #fff1f2;
        }

        pre {
            background: #111827;
            color: #e5e7eb;
            padding: 14px;
            border-radius: 12px;
            overflow-x: auto;
            white-space: pre-wrap;
        }

        @media (max-width: 900px) {
            .stats,
            .forms {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
<div class="container">

    <section class="header">
        <h1>Freshdesk Ticket Export</h1>
        <p>
            Fetch tickets from Freshdesk, store ticket conversations locally, and export clean JSON for AI analysis.
        </p>

        <div class="top-actions">
            <a class="button secondary" href="/freshchat/tickets">Raw Tickets JSON</a>
            <a class="button secondary" href="/freshchat/database">Local Database</a>
            <a class="button success" href="/freshchat/export-all?limit=10">Export All</a>
        </div>
    </section>

    @if (isset($tickets['error']))
        <section class="card error">
            <h2 class="card-title">API Error</h2>
            <p class="card-subtitle">Freshdesk did not return a normal ticket list.</p>
            <pre>{{ json_encode($tickets, JSON_PRETTY_PRINT) }}</pre>
        </section>
    @else
        <section class="stats">
            <div class="stat">
                <div class="stat-label">Tickets Loaded</div>
                <div class="stat-value">{{ count($tickets) }}</div>
            </div>

            <div class="stat">
                <div class="stat-label">Source</div>
                <div class="stat-value">Freshdesk</div>
            </div>

            <div class="stat">
                <div class="stat-label">Export Type</div>
                <div class="stat-value">JSON</div>
            </div>
        </section>

        <section class="card">
            <h2 class="card-title">Sync and Export</h2>
            <p class="card-subtitle">
                Use smaller date ranges first to avoid Freshdesk rate limits. For larger exports, use Export With Progress.
            </p>

            <div class="forms">
                <div class="form-box">
                    <h3>Sync tickets by period</h3>

                    <form class="form-row" action="/freshchat/sync" method="GET">
                        <div class="field">
                            <label for="start">Start</label>
                            <input id="start" name="start" type="date" required>
                        </div>

                        <div class="field">
                            <label for="end">End</label>
                            <input id="end" name="end" type="date" required>
                        </div>

                        <button type="submit">Sync</button>
                    </form>
                </div>

                <div class="form-box">
                    <h3>Quick batch export</h3>

                    <form class="form-row" action="/freshchat/export-batch" method="GET">
                        <div class="field">
                            <label for="export_start">Start</label>
                            <input id="export_start" name="start" type="date">
                        </div>

                        <div class="field">
                            <label for="export_end">End</label>
                            <input id="export_end" name="end" type="date">
                        </div>

                        <div class="field">
                            <label for="limit">Limit</label>
                            <input id="limit" name="limit" type="number" value="20" min="1" max="100">
                        </div>

                        <button class="success" type="submit">Export</button>
                    </form>
                </div>
            </div>
        </section>

        <section class="card">
            <h2 class="card-title">Tickets</h2>
            <p class="card-subtitle">Test, save, or export a ticket conversation.</p>

            @if (count($tickets) === 0)
                <div class="empty">No tickets found from the API.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach ($tickets as $ticket)
                            @if (is_array($ticket) && isset($ticket['id']))
                                <tr>
                                    <td>
                                        <span class="ticket-id">#{{ $ticket['id'] }}</span>
                                    </td>

                                    <td>
                                        <div class="subject">{{ $ticket['subject'] ?? 'No subject' }}</div>
                                        <div class="muted">{{ $ticket['email'] ?? $ticket['requester_id'] ?? 'No requester shown' }}</div>
                                    </td>

                                    <td>
                                        <span class="badge">{{ $ticket['status'] ?? 'N/A' }}</span>
                                    </td>

                                    <td class="muted">
                                        {{ $ticket['updated_at'] ?? 'N/A' }}
                                    </td>

                                    <td>
                                        <div class="row-actions">
                                            <a class="button small" href="/freshchat/test-api?ticket_id={{ $ticket['id'] }}">Test</a>
                                            <a class="button small success" href="/freshchat/save?ticket_id={{ $ticket['id'] }}">Save</a>
                                            <a class="button small secondary" href="/freshchat/export?ticket_id={{ $ticket['id'] }}">Export</a>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

</div>
</body>
</html>