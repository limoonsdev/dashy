<div>
    <x-slot:title>Appearance | Dashy</x-slot>

    <section class="application-settings-section mt-8 lg:mt-3">
        <div class="application-settings-section-header">
            <div>
                <h2>Language</h2>
                <p>Translations are written by hand, one locale at a time.</p>
            </div>
        </div>
        <div class="application-settings-section-body">
            <label class="text-[12px] text-neutral-500 dark:text-fg-faint" for="locale-picker">Interface language</label>
            <select id="locale-picker" class="input mt-1 w-full max-w-sm"
                onchange="if (this.value) window.location.href = this.value">
                @foreach (config('locales.supported') as $code => $meta)
                    <option value="{{ route('locale.switch', $code) }}" @selected(app()->getLocale() === $code)>
                        {{ $meta['native'] }} — {{ $meta['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </section>

    <x-theme-controls />
</div>
