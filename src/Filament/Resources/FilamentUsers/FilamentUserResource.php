<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers;

use CentivaDev\FilamentGoogleWorkspaceAuth\Exceptions\AccountProtectionException;
use CentivaDev\FilamentGoogleWorkspaceAuth\Support\AccountProtection;
use CentivaDev\FilamentGoogleWorkspaceAuth\Support\AccountStatus;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class FilamentUserResource extends Resource
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    public static function getModel(): string
    {
        return (string) config('filament-google-workspace-auth.user_model');
    }

    public static function getNavigationGroup(): ?string
    {
        return (string) (config('filament-google-workspace-auth.resources.navigation_group') ?? 'System');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->latest('last_login_at');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.sections.identity'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.email'))
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('avatar_url')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.avatar_url'))
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
                Section::make(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.sections.account'))
                    ->schema([
                        DateTimePicker::make('banned_at')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.banned_at'))
                            ->seconds(false)
                            ->disabled(fn (?Model $record) => static::deactivationLockReason($record) !== null)
                            ->helperText(fn (?Model $record) => static::deactivationLockReason($record)),
                        TextInput::make('google_sub')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.google_sub'))
                            ->disabled(),
                        DateTimePicker::make('last_login_at')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.last_login_at'))
                            ->seconds(false)
                            ->disabled(),
                    ]),
                Section::make(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.sections.access'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('roles')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.roles'))
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->optionsLimit(5)
                            ->relationship('roles', 'name', fn (Builder $query) => $query->where('guard_name', static::guardName()))
                            ->helperText(fn (?Model $record) => static::superAdminRemovalLockReason($record)),
                        Select::make('permissions')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.direct_permissions'))
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->optionsLimit(5)
                            ->relationship('permissions', 'name', fn (Builder $query) => $query->where('guard_name', static::guardName()))
                            ->helperText(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.helpers.direct_permissions')),
                        TextEntry::make('permissions_via_roles')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.permissions_via_roles'))
                            ->state(fn (?Model $record) => static::permissionsViaRoles($record))
                            ->badge()
                            ->placeholder(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.empty.permissions_via_roles'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.roles'))
                    ->badge()
                    ->separator(', '),
                TextColumn::make('last_login_at')
                    ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.last_login_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('banned_at')
                    ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.banned_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('ban')
                    ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.actions.ban'))
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => empty($record->banned_at))
                    ->disabled(fn (Model $record) => static::deactivationLockReason($record) !== null)
                    ->tooltip(fn (Model $record) => static::deactivationLockReason($record))
                    ->action(function (Model $record, Action $action) {
                        try {
                            $record->getConnection()->transaction(function () use ($record) {
                                app(AccountProtection::class)->ensureCanBan(static::actor() ?? abort(403), $record);

                                $record->setAttribute('banned_at', now());

                                if (AccountStatus::hasActiveColumn($record)) {
                                    $record->setAttribute('is_active', false);
                                }

                                $record->save();
                            });
                        } catch (AccountProtectionException $exception) {
                            Notification::make()
                                ->danger()
                                ->title(collect($exception->errors())->flatten()->first())
                                ->send();

                            $action->cancel();
                        }
                    }),
                Action::make('unban')
                    ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.actions.unban'))
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => ! empty($record->banned_at))
                    ->action(function (Model $record) {
                        $record->setAttribute('banned_at', null);

                        if (AccountStatus::hasActiveColumn($record)) {
                            $record->setAttribute('is_active', true);
                        }

                        $record->save();
                    }),
            ]);
    }

    public static function actor(): ?Model
    {
        $user = Auth::guard(static::guardName())->user();

        return $user instanceof Model ? $user : null;
    }

    public static function deactivationLockReason(?Model $record): ?string
    {
        $actor = static::actor();

        if (! $record || ! $actor || app(AccountProtection::class)->canDeactivate($actor, $record)) {
            return null;
        }

        return static::lockReason($actor, $record);
    }

    public static function superAdminRemovalLockReason(?Model $record): ?string
    {
        $actor = static::actor();

        if (! $record || ! $actor || app(AccountProtection::class)->canRemoveSuperAdmin($actor, $record)) {
            return null;
        }

        return static::lockReason($actor, $record);
    }

    /**
     * @return array<int, string>
     */
    protected static function permissionsViaRoles(?Model $record): array
    {
        if (! $record || ! method_exists($record, 'getPermissionsViaRoles')) {
            return [];
        }

        return $record->getPermissionsViaRoles()
            ->where('guard_name', static::guardName())
            ->pluck('name')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    protected static function lockReason(Model $actor, Model $record): string
    {
        $key = $actor->is($record) ? 'self' : 'last_super_admin';

        return (string) __('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.helpers.' . $key);
    }

    protected static function guardName(): string
    {
        return (string) config('filament-google-workspace-auth.guard', 'filament');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFilamentUsers::route('/'),
            'edit' => Pages\EditFilamentUser::route('/{record}/edit'),
        ];
    }
}
