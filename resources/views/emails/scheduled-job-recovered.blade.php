<x-emails.layout>
{{ $title }}

The last run succeeded after failed runs.

@if ($url)
Click [here]({{ $url }}) to open it in Dashy.
@endif
</x-emails.layout>
