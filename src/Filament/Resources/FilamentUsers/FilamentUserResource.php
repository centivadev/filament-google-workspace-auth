<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\FilamentUsers;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && method_exists($user, 'hasRole') && $user->hasRole('super-admin');
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
                        TextInput::make('google_sub')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.google_sub'))
                            ->disabled(),
                        TextInput::make('avatar_url')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.avatar_url'))
                            ->disabled(),
                    ]),
                Section::make(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.sections.access'))
                    ->columns(2)
                    ->schema([
                        Select::make('roles')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.roles'))
                            ->multiple()
                            ->relationship('roles', 'name'),
                        Select::make('permissions')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.permissions'))
                            ->multiple()
                            ->relationship('permissions', 'name'),
                        DateTimePicker::make('banned_at')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.banned_at'))
                            ->seconds(false),
                        DateTimePicker::make('last_login_at')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.fields.last_login_at'))
                            ->seconds(false)
                            ->disabled(),
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
            ->actions([
                EditAction::make(),
                Action::make('ban')
                    ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.actions.ban'))
                    ->requiresConfirmation()
                    ->visible(fn ($record) => empty($record->banned_at))
                    ->action(function ($record) {
                        $record->banned_at = now();
                        $record->is_active = false;
                        $record->save();
                    }),
                Action::make('unban')
                    ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.users.actions.unban'))
                    ->requiresConfirmation()
                    ->visible(fn ($record) => ! empty($record->banned_at))
                    ->action(function ($record) {
                        $record->banned_at = null;
                        $record->is_active = true;
                        $record->save();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFilamentUsers::route('/'),
            'edit' => Pages\EditFilamentUser::route('/{record}/edit'),
        ];
    }
}
