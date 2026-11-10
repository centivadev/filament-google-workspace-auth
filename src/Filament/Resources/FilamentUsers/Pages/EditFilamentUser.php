<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers\Pages;

use CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers\FilamentUserResource;
use CentivaDev\FilamentGoogleWorkspaceAuth\Support\AccountProtection;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

class EditFilamentUser extends EditRecord
{
    protected static string $resource = FilamentUserResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function beforeSave(): void
    {
        $state = $this->form->getRawState();
        $changes = [];

        foreach (['roles', 'banned_at'] as $field) {
            if ($this->form->getComponent($field, withHidden: true) && array_key_exists($field, $state)) {
                $changes[$field] = $state[$field];
            }
        }

        app(AccountProtection::class)->ensureCanUpdate(
            static::getResource()::actor() ?? abort(403),
            $this->getRecord(),
            $changes,
            'data.',
        );
    }

    protected function beginDatabaseTransaction(): void
    {
        if ($this->hasDatabaseTransactions()) {
            $this->getRecord()->getConnection()->beginTransaction();
        }
    }

    protected function commitDatabaseTransaction(): void
    {
        if ($this->hasDatabaseTransactions()) {
            $this->getRecord()->getConnection()->commit();
        }
    }

    protected function rollBackDatabaseTransaction(): void
    {
        if ($this->hasDatabaseTransactions()) {
            $this->getRecord()->getConnection()->rollBack();
        }
    }

    protected function afterSave(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
