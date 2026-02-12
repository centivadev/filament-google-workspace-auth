<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\Roles\Pages;

use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => ! RoleResource::isProtectedRole($this->record)),
        ];
    }
}
