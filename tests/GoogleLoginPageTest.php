<?php

use Filament\Facades\Filament;

it('renders the sign-in button in a full-width actions container', function () {
    $this->get(Filament::getPanel('admin')->getLoginUrl())
        ->assertOk()
        ->assertSee('fi-ac fi-width-full', escape: false)
        ->assertSee(route('filament-google-workspace-auth.redirect'), escape: false);
});
