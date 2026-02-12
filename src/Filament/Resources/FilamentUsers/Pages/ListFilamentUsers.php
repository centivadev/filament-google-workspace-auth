<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers\Pages;

use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers\FilamentUserResource;
use Filament\Resources\Pages\ListRecords;

class ListFilamentUsers extends ListRecords
{
    protected static string $resource = FilamentUserResource::class;
}
