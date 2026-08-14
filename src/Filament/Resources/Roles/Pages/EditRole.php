<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\Roles\Pages;

use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\Roles\RoleResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => $this->record instanceof Role && ! RoleResource::isProtectedRole($this->record)),
        ];
    }
}
