<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Domain\Enums\PackageTier;
use App\Filament\Platform\Resources\PackageResource\Pages;
use App\Models\Platform\Package;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PackageResource extends Resource
{
    protected static ?string $model = Package::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-plus';

    protected static ?string $navigationGroup = '平台管理';

    protected static ?string $modelLabel = '套餐';

    protected static ?string $pluralModelLabel = '套餐配置';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('套餐信息')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('tier')
                        ->label('套餐等级')
                        ->options(self::tierOptions())
                        ->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('name')
                        ->label('套餐名称')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('price_monthly')
                        ->label('月费')
                        ->numeric()
                        ->prefix('¥')
                        ->required(),
                    Forms\Components\TextInput::make('api_quota_daily')
                        ->label('每日 API 配额')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    Forms\Components\TextInput::make('merchant_limit')
                        ->label('商户账号上限')
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->default(1),
                    Forms\Components\Toggle::make('is_active')
                        ->label('启用')
                        ->default(true),
                    Forms\Components\KeyValue::make('features')
                        ->label('功能开关')
                        ->keyLabel('功能')
                        ->valueLabel('配置')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('套餐名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tier')
                    ->label('等级')
                    ->badge()
                    ->formatStateUsing(fn (PackageTier $state): string => $state->label())
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_monthly')
                    ->label('月费')
                    ->money('CNY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('api_quota_daily')
                    ->label('每日 API 配额')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('merchant_limit')
                    ->label('账号上限')
                    ->numeric(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('启用')
                    ->boolean(),
                Tables\Columns\TextColumn::make('tenants_count')
                    ->label('商户数')
                    ->counts('tenants')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('启用状态'),
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
            'index' => Pages\ListPackages::route('/'),
            'create' => Pages\CreatePackage::route('/create'),
            'edit' => Pages\EditPackage::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> */
    private static function tierOptions(): array
    {
        return collect(PackageTier::cases())
            ->mapWithKeys(fn (PackageTier $tier): array => [$tier->value => $tier->label()])
            ->all();
    }
}
