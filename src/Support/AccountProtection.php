<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Support;

use CentivaDev\FilamentGoogleWorkspaceAuth\Exceptions\AccountProtectionException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use Spatie\Permission\PermissionRegistrar;

/**
 * The ensure* methods lock the super-admin role, its pivot rows and its holders, so they must run
 * inside a transaction on the user model's connection, and that transaction must also perform
 * the write. The can* methods take no lock, reuse one read per request and are meant for the UI only.
 */
class AccountProtection
{
    public const SUPER_ADMIN_ROLE = 'super-admin';

    private const UPDATABLE = ['roles', 'is_active', 'banned_at'];

    /**
     * @var array<string, array{role: int|string|null, holders: array<string, Model>}>
     */
    private array $unlocked = [];

    /**
     * @param  array<string, mixed>  $changes  Only the submitted keys: 'roles' (ids of the target's
     *                                         roles in the configured guard after the save),
     *                                         'is_active', 'banned_at'.
     *
     * @throws AccountProtectionException
     */
    public function ensureCanUpdate(Model $actor, Model $target, array $changes, string $errorKeyPrefix = ''): void
    {
        $unknown = array_diff(array_keys($changes), self::UPDATABLE);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unsupported account changes: ' . implode(', ', $unknown) . '.');
        }

        $this->ensureTransaction($target);

        if (! AccountStatus::hasActiveColumn($target)) {
            unset($changes['is_active']);
        }

        ['role' => $roleKey, 'holders' => $holders] = $this->superAdmins($target, lock: true);

        $key = (string) $target->getKey();
        $holdsRole = isset($holders[$key]);
        $keepsRole = ! array_key_exists('roles', $changes)
            || in_array((string) $roleKey, array_map('strval', (array) $changes['roles']), true);
        $isSelf = $actor->is($target);

        $errors = [];

        if ($isSelf && $holdsRole && ! $keepsRole) {
            $errors['roles'] = $this->message('self_super_admin');
        }

        if ($isSelf && array_key_exists('is_active', $changes) && ! (bool) $changes['is_active']) {
            $errors['is_active'] = $this->message('self_deactivate');
        }

        if ($isSelf && ! empty($changes['banned_at'])) {
            $errors['banned_at'] = $this->message('self_deactivate');
        }

        if ($errors === [] && $this->isLast($holders, $key)) {
            $after = clone $holders[$key];

            foreach (['is_active', 'banned_at'] as $attribute) {
                if (array_key_exists($attribute, $changes)) {
                    $after->setAttribute($attribute, blank($changes[$attribute]) ? null : $changes[$attribute]);
                }
            }

            if (! $keepsRole) {
                $errors['roles'] = $this->message('last_super_admin');
            } elseif (! AccountStatus::allowsSignIn($after)) {
                $errors[AccountStatus::isActive($after) ? 'banned_at' : 'is_active'] = $this->message('last_super_admin');
            }
        }

        if ($errors !== []) {
            $messages = [];

            foreach ($errors as $field => $message) {
                $messages[$errorKeyPrefix . $field] = $message;
            }

            throw AccountProtectionException::withMessages($messages);
        }
    }

    /**
     * @throws AccountProtectionException
     */
    public function ensureCanBan(Model $actor, Model $target): void
    {
        $this->ensureCanUpdate($actor, $target, ['banned_at' => now(), 'is_active' => false]);
    }

    /**
     * @throws AccountProtectionException
     */
    public function ensureCanDelete(Model $target): void
    {
        $this->ensureTransaction($target);

        if ($this->isLast($this->superAdmins($target, lock: true)['holders'], (string) $target->getKey())) {
            throw AccountProtectionException::withMessages(['record' => $this->message('last_super_admin_delete')]);
        }
    }

    public function canRemoveSuperAdmin(Model $actor, Model $target): bool
    {
        $holders = $this->superAdmins($target, lock: false)['holders'];
        $key = (string) $target->getKey();

        return ! isset($holders[$key]) || (! $actor->is($target) && ! $this->isLast($holders, $key));
    }

    public function canDeactivate(Model $actor, Model $target): bool
    {
        return ! $actor->is($target) && ! $this->isLastActiveSuperAdmin($target);
    }

    public function canDelete(Model $target): bool
    {
        return ! $this->isLastActiveSuperAdmin($target);
    }

    public function isLastActiveSuperAdmin(Model $target): bool
    {
        return $this->isLast($this->superAdmins($target, lock: false)['holders'], (string) $target->getKey());
    }

    /**
     * @return array{role: int|string|null, holders: array<string, Model>}
     */
    protected function superAdmins(Model $target, bool $lock): array
    {
        $cacheKey = $target->getConnectionName() . '|' . $target::class;

        if (! $lock) {
            return $this->unlocked[$cacheKey] ??= $this->readSuperAdmins($target, lock: false);
        }

        $this->unlocked = [];

        return $this->readSuperAdmins($target, lock: true);
    }

    /**
     * @return array{role: int|string|null, holders: array<string, Model>}
     */
    private function readSuperAdmins(Model $target, bool $lock): array
    {
        $registrar = app(PermissionRegistrar::class);

        /** @var Model $roleModel */
        $roleModel = app($registrar->getRoleClass());

        $roleQuery = $roleModel->newQuery()
            ->where('name', self::SUPER_ADMIN_ROLE)
            ->where('guard_name', (string) config('filament-google-workspace-auth.guard', 'filament'));

        $role = ($lock ? $roleQuery->lockForUpdate() : $roleQuery)->first();

        if (! $role) {
            return ['role' => null, 'holders' => []];
        }

        $pivotQuery = $target->getConnection()
            ->table((string) config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->where($registrar->pivotRole, $role->getKey())
            ->where('model_type', $target->getMorphClass());

        $ids = ($lock ? $pivotQuery->lockForUpdate() : $pivotQuery)
            ->pluck((string) config('permission.column_names.model_morph_key', 'model_id'))
            ->all();

        $usersQuery = $target->newQuery()->whereKey($ids);

        $holders = [];

        foreach (($lock ? $usersQuery->lockForUpdate() : $usersQuery)->get() as $user) {
            $holders[(string) $user->getKey()] = $user;
        }

        return ['role' => $role->getKey(), 'holders' => $holders];
    }

    /**
     * @param  array<string, Model>  $holders
     */
    protected function isLast(array $holders, string $key): bool
    {
        $allowed = array_filter($holders, fn (Model $user): bool => AccountStatus::allowsSignIn($user));

        return isset($allowed[$key]) && count($allowed) === 1;
    }

    protected function ensureTransaction(Model $target): void
    {
        if ($target->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Account protection checks must run inside a database transaction on the user model connection.');
        }
    }

    protected function message(string $key): string
    {
        return (string) __('filament-google-workspace-auth::filament-google-workspace-auth.protection.' . $key);
    }
}
