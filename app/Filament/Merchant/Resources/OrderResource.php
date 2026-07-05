<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources;

use App\Domain\Enums\OrderStatus;
use App\Filament\Merchant\Resources\OrderResource\Pages;
use App\Models\Order\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = '经营管理';

    protected static ?string $modelLabel = '订单';

    protected static ?string $pluralModelLabel = '订单管理';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('订单信息')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('order_no')
                        ->label('订单号')
                        ->required()
                        ->maxLength(80)
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('buyer_name')
                        ->label('买家姓名')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('buyer_phone')
                        ->label('买家电话')
                        ->required()
                        ->maxLength(50),
                    Forms\Components\TextInput::make('total_amount')
                        ->label('订单金额')
                        ->numeric()
                        ->prefix('¥')
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->label('状态')
                        ->options(self::statusOptions())
                        ->required()
                        ->default(OrderStatus::PendingPayment->value),
                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label('付款时间')
                        ->seconds(false),
                    Forms\Components\DateTimePicker::make('shipped_at')
                        ->label('发货时间')
                        ->seconds(false),
                    Forms\Components\DateTimePicker::make('cancelled_at')
                        ->label('取消时间')
                        ->seconds(false),
                    Forms\Components\TextInput::make('cancel_reason')
                        ->label('取消原因')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_no')
                    ->label('订单号')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('buyer_name')
                    ->label('买家')
                    ->searchable(),
                Tables\Columns\TextColumn::make('buyer_phone')
                    ->label('电话')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('金额')
                    ->money('CNY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::PendingPayment => 'warning',
                        OrderStatus::Paid, OrderStatus::Shipped => 'info',
                        OrderStatus::Completed => 'success',
                        OrderStatus::RefundRequested => 'danger',
                        OrderStatus::Cancelled => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('下单时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options(self::statusOptions()),
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(OrderStatus::cases())
            ->mapWithKeys(fn (OrderStatus $status): array => [$status->value => $status->label()])
            ->all();
    }
}
