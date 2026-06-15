<x-filament-panels::page.simple>
    <x-filament::button
        tag="a"
        color="primary"
        size="lg"
        icon="heroicon-m-globe-alt"
        href="{{ $this->getGoogleRedirectUrl() }}"
        class="w-full justify-center"
    >
        {{ __('filament-google-workspace-auth::filament-google-workspace-auth.login.button') }}
    </x-filament::button>
</x-filament-panels::page.simple>
