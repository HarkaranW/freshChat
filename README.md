# Freshchat Export & Centralisation - Laravel All Phases

This is the clean Laravel version for the Freshchat project.

It covers:
- Phase 1: Fetch one conversation and store it.
- Phase 2: Sync conversations by period.
- Phase 3: Export AI-ready JSON, including batch export.

## Important security rule

Do NOT put the real API key directly inside PHP files. Put it only in `.env`.

## Setup

Create a Laravel project first:

```bash
composer create-project laravel/laravel freshchat-project
cd freshchat-project
```

Copy these folders from this zip into your Laravel project:

```text
app/
config/
database/
resources/
routes/
```

## Environment variables

Add this to `.env`:

```env
FRESHCHAT_USE_FAKE=false
FRESHCHAT_BASE_URL=https://your-company.myfreshworks.com
FRESHCHAT_API_KEY=your_real_api_key_here
```

To test without the real API:

```env
FRESHCHAT_USE_FAKE=true
```

## Database

For SQLite testing:

```env
DB_CONNECTION=sqlite
```

Create the file:

Windows PowerShell:

```powershell
New-Item database/database.sqlite -ItemType File
```

Mac / Linux:

```bash
touch database/database.sqlite
```

Then run:

```bash
php artisan migrate
```

## Run server

```bash
php artisan serve
```

Open:

```text
http://127.0.0.1:8000/freshchat
```

## Browser routes

```text
/freshchat
/freshchat/test-api?conversation_id=YOUR_CONVERSATION_ID
/freshchat/save?conversation_id=YOUR_CONVERSATION_ID
/freshchat/sync?start=2026-04-01&end=2026-04-28
/freshchat/database
/freshchat/export?conversation_id=YOUR_CONVERSATION_ID
/freshchat/export-batch?start=2026-04-01&end=2026-04-28&limit=100
```

## Artisan commands

Phase 1:

```bash
php artisan freshchat:sync-one YOUR_CONVERSATION_ID
```

Phase 2:

```bash
php artisan freshchat:sync --start=2026-04-01 --end=2026-04-28
```

Phase 3:

```bash
php artisan freshchat:export --conversation=YOUR_CONVERSATION_ID
php artisan freshchat:export --start=2026-04-01 --end=2026-04-28 --limit=100
```

Exports are saved in:

```text
storage/app/freshchat_exports/
```

## Notes

The confirmed endpoint from the project PDF is:

```text
GET /v2/conversations/{conversation_id}/messages
```

For period sync, this project includes:

```text
GET /v2/conversations
```

If Freshchat returns a different response shape, update only:

```text
app/Services/FreshchatService.php
```
