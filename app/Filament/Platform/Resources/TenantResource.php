<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Domain\Enums\TenantStatus;
use App\Filament\Platform\Resources\TenantResource\Pages;
use App\Http\Controllers\ImpersonationController;
use App\Models\Tenant\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = '平台管理';

    protected static ?string $modelLabel = '商户';

    protected static ?string $pluralModelLabel = '商户管理';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('商户信息')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('merchant_code')
                        ->label('商户编号')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('name')
                        ->label('商户名称')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('contact_name')
                        ->label('联系人')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('contact_phone')
                        ->label('联系电话')
                        ->required()
                        ->maxLength(50),
                    Forms\Components\Select::make('package_id')
                        ->label('套餐')
                        ->relationship('package', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->label('状态')
                        ->options(self::tenantStatusOptions())
                        ->required()
                        ->default(TenantStatus::Enabled->value),
                    Forms\Components\TextInput::make('commission_rate')
                        ->label('佣金比例')
                        ->numeric()
                        ->step('0.0001')
                        ->required()
                        ->default('0.0200'),
                    Forms\Components\DateTimePicker::make('joined_at')
                        ->label('入驻时间')
                        ->seconds(false)
                        ->required()
                        ->default(now()),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('merchant_code')
                    ->label('商户编号')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('商户名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('package.name')
                    ->label('套餐')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (TenantStatus $state): string => $state->label())
                    ->color(fn (TenantStatus $state): string => $state === TenantStatus::Enabled ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('contact_name')
                    ->label('联系人')
                    ->searchable(),
                Tables\Columns\TextColumn::make('contact_phone')
                    ->label('联系电话')
                    ->searchable(),
                Tables\Columns\TextColumn::make('joined_at')
                    ->label('入驻时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options(self::tenantStatusOptions()),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('impersonate')
                    ->label('进入商户后台')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (Tenant $record) {
                        app(ImpersonationController::class)->impersonate($record);

                        return redirect('/merchant');
                    }),
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
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
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
    private static function tenantStatusOptions(): array
    {
        return collect(TenantStatus::cases())
            ->mapWithKeys(fn (TenantStatus $status): array => [$status->value => $status->label()])
            ->all();
    }
}
