<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\Permissions\Pages;

use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\Permissions\PermissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPermissions extends ListRecords
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
