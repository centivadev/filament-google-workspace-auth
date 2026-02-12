<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Filament\Resources\Permissions;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-key';

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
                Section::make(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.permissions.sections.main'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.permissions.fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: function ($rule) {
                                    $guard = (string) config('filament-google-workspace-auth.guard', 'filament');

                                    return $rule->where('guard_name', $guard);
                                }
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-google-workspace-auth::filament-google-workspace-auth.filament.permissions.fields.name'))
                    ->searchable()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
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
            'index' => Pages\ListPermissions::route('/'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
            'create' => Pages\CreatePermission::route('/create'),
        ];
    }
}
