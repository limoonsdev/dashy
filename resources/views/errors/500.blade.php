@extends('layouts.base')

@section('body')
    <body class="error-page-body text-black dark:text-inherit">
        <x-toast />
        <x-error-page
            code="500"
            title="Something broke on our side."
            description="Dashy hit an unexpected error while handling this request. The details below help you (or whoever runs this instance) fix it."
            tone="danger">
            @if ($exception->getMessage() !== '')
                <div class="error-message">
                    {!! Purify::clean($exception->getMessage()) !!}
                </div>
            @endif
        </x-error-page>
    </body>
@endsection
