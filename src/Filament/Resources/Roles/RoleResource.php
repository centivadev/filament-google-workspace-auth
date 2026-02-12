<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\Roles;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected const PROTECTED_ROLE_NAMES = ['super-admin', 'guest'];

    protected static ?string $model = Role::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';

    public static function getNavigationGroup(): ?string
    {
        return (string) (config('filament-google-workspace-auth.resources.navigation_group') ?? 'System');
    }

    public static function getEloquentQuery(): Builder
    {
        $guard = (string) config('filament-google-workspace-auth.guard', 'filament');

        return parent::getEloquentQuery()->where('guard_name', $guard);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.roles.sections.main'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.roles.fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn (?Role $record) => $record && static::isProtectedRole($record))
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: function ($rule) {
                                    $guard = (string) config('filament-google-workspace-auth.guard', 'filament');

                                    return $rule->where('guard_name', $guard);
                                }
                            ),
                        Select::make('permissions')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.roles.fields.permissions'))
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->optionsLimit(5)
                            ->relationship('permissions', 'name'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.roles.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('permissions_count')
                    ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.roles.fields.permissions'))
                    ->counts('permissions'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Role $record) => ! static::isProtectedRole($record)),
            ]);
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['guard_name'] = (string) config('filament-google-workspace-auth.guard', 'filament');

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
            'create' => Pages\CreateRole::route('/create'),
        ];
    }

    public static function isProtectedRole(Role $role): bool
    {
        return in_array($role->name, static::PROTECTED_ROLE_NAMES, true);
    }
}
