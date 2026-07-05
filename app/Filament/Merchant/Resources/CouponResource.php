<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources;

use App\Domain\Enums\CouponStatus;
use App\Domain\Enums\CouponType;
use App\Filament\Merchant\Resources\CouponResource\Pages;
use App\Models\Marketing\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = '经营管理';

    protected static ?string $modelLabel = '优惠券';

    protected static ?string $pluralModelLabel = '营销优惠';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('优惠券信息')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('名称')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('type')
                        ->label('类型')
                        ->options(self::typeOptions())
                        ->required()
                        ->default(CouponType::FullReduction->value),
                    Forms\Components\Select::make('status')
                        ->label('状态')
                        ->options(self::statusOptions())
                        ->required()
                        ->default(CouponStatus::NotStarted->value),
                    Forms\Components\TextInput::make('discount_value')
                        ->label('优惠值')
                        ->numeric()
                        ->required(),
                    Forms\Components\TextInput::make('min_amount')
                        ->label('使用门槛')
                        ->numeric()
                        ->default(0)
                        ->required(),
                    Forms\Components\TextInput::make('usage_limit')
                        ->label('发放上限')
                        ->numeric()
                        ->minValue(1),
                    Forms\Components\TextInput::make('used_count')
                        ->label('已使用')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('开始时间')
                        ->seconds(false),
                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label('结束时间')
                        ->seconds(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('类型')
                    ->badge()
                    ->formatStateUsing(fn (CouponType $state): string => $state->label()),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (CouponStatus $state): string => $state->label())
                    ->color(fn (CouponStatus $state): string => match ($state) {
                        CouponStatus::NotStarted => 'gray',
                        CouponStatus::Active => 'success',
                        CouponStatus::Ended => 'warning',
                    }),
                Tables\Columns\TextColumn::make('discount_value')
                    ->label('优惠值')
                    ->numeric(2),
                Tables\Columns\TextColumn::make('min_amount')
                    ->label('门槛')
                    ->money('CNY'),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('已使用')
                    ->numeric(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('结束时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options(self::statusOptions()),
                Tables\Filters\SelectFilter::make('type')
                    ->label('类型')
                    ->options(self::typeOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> */
    private static function typeOptions(): array
    {
        return collect(CouponType::cases())
            ->mapWithKeys(fn (CouponType $type): array => [$type->value => $type->label()])
            ->all();
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(CouponStatus::cases())
            ->mapWithKeys(fn (CouponStatus $status): array => [$status->value => $status->label()])
            ->all();
    }
}
