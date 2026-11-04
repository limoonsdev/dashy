{{ Illuminate\Mail\Markdown::parse('---') }}

Thank you,<br>
{{ config('app.name') ?? 'Dashy' }}

{{ Illuminate\Mail\Markdown::parse('[Contact Support](https://dashy.io/docs/contact)') }}
