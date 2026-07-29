<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources;

use App\Domain\Enums\OperationAction;
use App\Domain\Enums\OrderStatus;
use App\Filament\Merchant\Resources\OperationLogResource\Pages;
use App\Models\System\OperationLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OperationLogResource extends Resource
{
    protected static ?string $model = OperationLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = '经营管理';

    protected static ?string $modelLabel = '操作日志';

    protected static ?string $pluralModelLabel = '操作审计';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('操作详情')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('actor_label')
                        ->label('操作者'),
                    Forms\Components\TextInput::make('action')
                        ->label('动作'),
                    Forms\Components\TextInput::make('subject_label')
                        ->label('对象'),
                    Forms\Components\TextInput::make('from_status')
                        ->label('原状态'),
                    Forms\Components\TextInput::make('to_status')
                        ->label('新状态'),
                    Forms\Components\KeyValue::make('payload')
                        ->label('详情')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('actor_label')
                    ->label('操作者'),
                Tables\Columns\TextColumn::make('actor_kind')
                    ->label('来源')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('action')
                    ->label('动作')
                    ->badge()
                    ->formatStateUsing(fn (OperationAction $state): string => $state->label())
                    ->color(fn (OperationAction $state): string => match ($state) {
                        OperationAction::Created => 'success',
                        OperationAction::Shipped => 'info',
                        OperationAction::Cancelled => 'gray',
                        OperationAction::RefundRequested => 'danger',
                        OperationAction::Updated => 'warning',
                    }),
                Tables\Columns\TextColumn::make('subject_label')
                    ->label('对象')
                    ->searchable(),
                Tables\Columns\TextColumn::make('from_status')
                    ->label('原状态')
                    ->formatStateUsing(fn (?string $state): string => ($state ? OrderStatus::tryFrom($state) : null)?->label() ?? '-'),
                Tables\Columns\TextColumn::make('to_status')
                    ->label('新状态')
                    ->formatStateUsing(fn (?string $state): string => ($state ? OrderStatus::tryFrom($state) : null)?->label() ?? '-'),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('动作')
                    ->options(OperationAction::options()),
                Filter::make('created_at')
                    ->label('操作时间')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->label('开始'),
                        Forms\Components\DatePicker::make('created_until')->label('结束'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['created_until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListOperationLogs::route('/'),
            'view' => Pages\ViewOperationLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }
}
