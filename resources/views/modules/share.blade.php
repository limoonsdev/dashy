<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $record->title }} · Dashy</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; font: 15px/1.6 ui-sans-serif, system-ui, 'Segoe UI', sans-serif; background: #fafafa; color: #111; }
        main { max-width: 780px; margin: 0 auto; padding: 48px 20px 80px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #6b16ed14; color: #6b16ed; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        h1 { margin: 12px 0 20px; font-size: 28px; line-height: 1.25; }
        .meta { color: #666; font-size: 12px; margin-bottom: 24px; }
        pre { white-space: pre-wrap; word-break: break-word; background: #fff; border: 1px solid #e5e5e5; border-radius: 10px; padding: 16px; font: 13px/1.6 ui-monospace, Consolas, monospace; }
        footer { margin-top: 32px; color: #888; font-size: 12px; }
        @media (prefers-color-scheme: dark) {
            body { background: #101010; color: #eee; }
            pre { background: #191919; border-color: #2a2a2a; }
            .badge { background: #fcd4521a; color: #fcd452; }
        }
    </style>
</head>
<body>
    <main>
        <span class="badge">{{ \App\Models\ModuleRecord::MODULES[$record->module] ?? $record->module }}</span>
        <h1>{{ $record->title }}</h1>
        <p class="meta">
            {{ $record->status }}
            @if (! empty($record->payload['start'])) · {{ $record->payload['start'] }} @endif
            · updated {{ $record->updated_at->toFormattedDateString() }}
        </p>

        @if (! empty($record->payload['body']))
            <pre>{{ $record->payload['body'] }}</pre>
        @endif

        @if (! empty($record->payload['recipients']))
            <p class="meta">Signers: {{ implode(', ', (array) $record->payload['recipients']) }}</p>
        @endif

        <footer>Shared from Dashy. This page is read-only.</footer>
    </main>
</body>
</html>
