<x-filament-panels::page.simple>
    <x-filament::actions full-width>
        <x-filament::button
            tag="a"
            color="primary"
            size="lg"
            icon="heroicon-m-globe-alt"
            href="{{ $this->getGoogleRedirectUrl() }}"
        >
            {{ __('filament-google-workspace-auth::filament-google-workspace-auth.login.button') }}
        </x-filament::button>
    </x-filament::actions>
</x-filament-panels::page.simple>
