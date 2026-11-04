@props([
    'code',
    'title',
    'description' => null,
    'showGoBack' => true,
    'showDashboard' => true,
    'primaryHref' => null,
    'primaryLabel' => null,
    'tone' => 'accent', // accent | danger
    'showContact' => true,
])

@php
    $eyebrows = [
        '400' => 'Bad request',
        '401' => 'Authentication required',
        '402' => 'Payment required',
        '403' => 'Access denied',
        '404' => 'Page not found',
        '419' => 'Session expired',
        '429' => 'Too many requests',
        '500' => 'Internal error',
        '503' => 'Temporarily unavailable',
    ];
    $eyebrow = $eyebrows[(string) $code] ?? 'Unexpected response';
    $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'self-hosted';
@endphp

<main {{ $attributes->merge(['class' => 'error-shell'.($tone === 'danger' ? ' error-shell-danger' : '')]) }}>
    <div class="error-aurora" aria-hidden="true">
        <span class="error-aurora-blob error-aurora-blob-a"></span>
        <span class="error-aurora-blob error-aurora-blob-b"></span>
        <span class="error-aurora-blob error-aurora-blob-c"></span>
    </div>
    <div class="error-grid" aria-hidden="true"></div>

    <div class="error-card">
        <div class="error-card-top">
            <span class="error-pill">
                <span class="error-pill-dot" aria-hidden="true"></span>
                {{ $eyebrow }}
            </span>
            <span class="error-brand">Dashy</span>
        </div>

        <div class="error-code-row">
            <p class="error-code">{{ $code }}</p>
            <div class="error-signal" aria-hidden="true">
                <span></span><span></span><span></span><span></span><span></span><span></span>
            </div>
        </div>

        <h1 class="error-title">{{ $title }}</h1>

        @if (filled($description))
            <p class="error-description">{{ $description }}</p>
        @endif

        @if ($slot->isNotEmpty())
            <div class="error-extra">
                {{ $slot }}
            </div>
        @endif

        <div class="error-actions">
            @if (filled($primaryHref) && filled($primaryLabel))
                <a href="{{ $primaryHref }}">
                    <x-forms.button type="button">{{ $primaryLabel }}</x-forms.button>
                </a>
            @endif

            @if ($showGoBack)
                <a href="{{ url()->previous() }}">
                    <x-forms.button type="button">Go back</x-forms.button>
                </a>
            @endif

            @if ($showDashboard)
                <a href="{{ route('dashboard') }}" {{ wireNavigate() }}>
                    <x-forms.button type="button">Dashboard</x-forms.button>
                </a>
            @endif

            @if ($showContact)
                <a
                    target="_blank"
                    rel="noopener noreferrer"
                    href="{{ config('constants.urls.contact') }}">
                    <x-forms.button type="button">
                        Contact support
                        <x-external-link class="inline-flex size-3 text-current" />
                    </x-forms.button>
                </a>
            @endif
        </div>

        <div class="error-footnote">
            <span class="error-footnote-tag">Dashy</span>
            <span class="error-footnote-sep" aria-hidden="true"></span>
            <span class="error-footnote-host">{{ $host }}</span>
            <span class="error-footnote-code">HTTP {{ $code }}</span>
        </div>
    </div>
</main>
