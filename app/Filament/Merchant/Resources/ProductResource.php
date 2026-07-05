<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources;

use App\Domain\Enums\ProductStatus;
use App\Filament\Merchant\Resources\ProductResource\Pages;
use App\Models\Product\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = '经营管理';

    protected static ?string $modelLabel = '商品';

    protected static ?string $pluralModelLabel = '商品管理';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('商品信息')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('product_code')
                        ->label('商品编码')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('name')
                        ->label('商品名称')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('price')
                        ->label('价格')
                        ->numeric()
                        ->prefix('¥')
                        ->required(),
                    Forms\Components\TextInput::make('stock')
                        ->label('库存')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->default(0),
                    Forms\Components\TextInput::make('sales_count')
                        ->label('销量')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->default(0),
                    Forms\Components\Select::make('status')
                        ->label('状态')
                        ->options(self::statusOptions())
                        ->required()
                        ->default(ProductStatus::Listed->value),
                    Forms\Components\TextInput::make('cover_image')
                        ->label('封面图片')
                        ->url()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Forms\Components\KeyValue::make('specs')
                        ->label('规格')
                        ->keyLabel('规格名')
                        ->valueLabel('规格值')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product_code')
                    ->label('商品编码')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('商品名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('价格')
                    ->money('CNY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->label('库存')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sales_count')
                    ->label('销量')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (ProductStatus $state): string => $state->label())
                    ->color(fn (ProductStatus $state): string => $state === ProductStatus::Listed ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options(self::statusOptions()),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(ProductStatus::cases())
            ->mapWithKeys(fn (ProductStatus $status): array => [$status->value => $status->label()])
            ->all();
    }
}
