<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources;

use App\Domain\Enums\BillStatus;
use App\Filament\Merchant\Resources\TenantBillResource\Pages;
use App\Models\Billing\TenantBill;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TenantBillResource extends Resource
{
    protected static ?string $model = TenantBill::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-yen';

    protected static ?string $navigationGroup = '财务';

    protected static ?string $modelLabel = '月度账单';

    protected static ?string $pluralModelLabel = '月度账单';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('账单信息')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('billing_period')
                        ->label('账期')
                        ->required()
                        ->maxLength(7),
                    Forms\Components\Select::make('status')
                        ->label('状态')
                        ->options(self::statusOptions())
                        ->required(),
                    Forms\Components\TextInput::make('transaction_total')
                        ->label('交易总额')
                        ->numeric()
                        ->prefix('¥')
                        ->required(),
                    Forms\Components\TextInput::make('commission_amount')
                        ->label('佣金')
                        ->numeric()
                        ->prefix('¥')
                        ->required(),
                    Forms\Components\TextInput::make('api_usage_fee')
                        ->label('API 基础费用')
                        ->numeric()
                        ->prefix('¥')
                        ->required(),
                    Forms\Components\TextInput::make('api_overage_fee')
                        ->label('API 超额费用')
                        ->numeric()
                        ->prefix('¥')
                        ->required(),
                    Forms\Components\TextInput::make('total_receivable')
                        ->label('应收合计')
                        ->numeric()
                        ->prefix('¥')
                        ->required(),
                    Forms\Components\TextInput::make('merchant_reported_amount')
                        ->label('商户实报')
                        ->numeric()
                        ->prefix('¥'),
                    Forms\Components\TextInput::make('difference_amount')
                        ->label('差异金额')
                        ->numeric()
                        ->prefix('¥'),
                    Forms\Components\TextInput::make('payment_channel')
                        ->label('支付渠道')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('external_transaction_no')
                        ->label('外部交易号')
                        ->maxLength(100),
                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label('支付时间')
                        ->seconds(false),
                    Forms\Components\KeyValue::make('payment_meta')
                        ->label('支付扩展信息')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('billing_period')
                    ->label('账期')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_total')
                    ->label('交易总额')
                    ->money('CNY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_amount')
                    ->label('佣金')
                    ->money('CNY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('api_overage_fee')
                    ->label('超额费')
                    ->money('CNY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_receivable')
                    ->label('应收')
                    ->money('CNY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('merchant_reported_amount')
                    ->label('实报')
                    ->money('CNY')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (BillStatus $state): string => $state->label())
                    ->color(fn (BillStatus $state): string => match ($state) {
                        BillStatus::PendingSettlement => 'warning',
                        BillStatus::Settled => 'success',
                        BillStatus::Overdue => 'danger',
                    }),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('支付时间')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-'),
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
            'index' => Pages\ListTenantBills::route('/'),
            'edit' => Pages\EditTenantBill::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(BillStatus::cases())
            ->mapWithKeys(fn (BillStatus $status): array => [$status->value => $status->label()])
            ->all();
    }
}
