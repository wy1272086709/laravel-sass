<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\PlatformRoleResource\Pages;
use App\Models\Platform\PlatformRole;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlatformRoleResource extends Resource
{
    protected static ?string $model = PlatformRole::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = '平台管理';

    protected static ?string $modelLabel = '平台角色';

    protected static ?string $pluralModelLabel = '角色权限';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('角色信息')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('角色名称')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('slug')
                        ->label('角色标识')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Textarea::make('description')
                        ->label('说明')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\CheckboxList::make('permissions')
                        ->label('权限')
                        ->relationship('permissions', 'name')
                        ->columns(2)
                        ->searchable()
                        ->bulkToggleable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('角色名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('标识')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('权限数')
                    ->counts('permissions')
                    ->sortable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('用户数')
                    ->counts('users')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformRoles::route('/'),
            'create' => Pages\CreatePlatformRole::route('/create'),
            'edit' => Pages\EditPlatformRole::route('/{record}/edit'),
        ];
    }
}
