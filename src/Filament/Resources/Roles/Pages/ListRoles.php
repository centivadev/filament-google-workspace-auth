<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\Roles\Pages;

use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\Roles\RoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
