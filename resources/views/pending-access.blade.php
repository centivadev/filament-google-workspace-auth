<x-filament-panels::page.simple>
    <p class="fi-simple-header-subheading" style="margin-top: 0">
        {{ __('filament-google-workspace-auth::filament-google-workspace-auth.pending_access.recognized') }}
    </p>

    @if ($supportEmail = $this->getSupportEmail())
        <p class="fi-simple-header-subheading" style="margin-top: 0">
            {{ __('filament-google-workspace-auth::filament-google-workspace-auth.pending_access.contact') }}<br />
            <x-filament::link href="mailto:{{ $supportEmail }}">
                {{ $supportEmail }}
            </x-filament::link>
        </p>
    @endif

    <form method="POST" action="{{ filament()->getLogoutUrl() }}">
        @csrf

        <x-filament::actions full-width>
            <x-filament::button type="submit" color="gray">
                {{ __('filament-google-workspace-auth::filament-google-workspace-auth.pending_access.logout') }}
            </x-filament::button>
        </x-filament::actions>
    </form>
</x-filament-panels::page.simple>
