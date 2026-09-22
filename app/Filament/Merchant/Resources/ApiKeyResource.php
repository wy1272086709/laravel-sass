<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Resources;

use App\Domain\Enums\ApiKeyStatus;
use App\Domain\Enums\ApiPermission;
use App\Filament\Merchant\Resources\ApiKeyResource\Pages;
use App\Models\Api\ApiKey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = '开放能力';

    protected static ?string $modelLabel = 'API 密钥';

    protected static ?string $pluralModelLabel = 'API 密钥';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('密钥信息')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('名称')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('app_key')
                        ->label('App Key')
                        ->required()
                        ->maxLength(80)
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('app_secret')
                        ->label('App Secret')
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->afterStateUpdated(fn (?string $state, Forms\Set $set): mixed => filled($state) ? $set('signing_secret', $state) : null)
                        ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                        ->helperText('保存后不再展示明文；如需轮换，请输入新密钥。'),
                    Forms\Components\Hidden::make('signing_secret')
                        ->dehydrated(fn (?string $state): bool => filled($state)),
                    Forms\Components\Select::make('status')
                        ->label('状态')
                        ->options(self::statusOptions())
                        ->required()
                        ->default(ApiKeyStatus::Enabled->value),
                    Forms\Components\CheckboxList::make('permissions')
                        ->label('权限')
                        ->options(self::permissionOptions())
                        ->columns(2)
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\DateTimePicker::make('last_used_at')
                        ->label('最后使用')
                        ->seconds(false)
                        ->disabled()
                        ->dehydrated(false),
                ]),
            Forms\Components\Section::make('IP 访问控制')
                ->description('留空 = 不限制。黑名单命中直接拒绝，优先级高于白名单。')
                ->columns(2)
                ->schema([
                    Forms\Components\TagsInput::make('ip_whitelist')
                        ->label('IP 白名单')
                        ->placeholder('如 203.0.113.5')
                        ->helperText('配置后仅允许名单内 IP 调用；支持精确 IP、CIDR（203.0.113.0/24）、通配（203.0.113.*）。'),
                    Forms\Components\TagsInput::make('ip_blacklist')
                        ->label('IP 黑名单')
                        ->placeholder('如 198.51.100.9')
                        ->helperText('命中名单的 IP 一律拒绝（403 / 业务码 40302），格式同白名单。'),
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
                Tables\Columns\TextColumn::make('app_key')
                    ->label('App Key')
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('permissions')
                    ->label('权限')
                    ->formatStateUsing(fn (mixed $state): string => self::formatPermissions($state))
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (ApiKeyStatus $state): string => $state->label())
                    ->color(fn (ApiKeyStatus $state): string => $state === ApiKeyStatus::Enabled ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('最后使用')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('从未使用')
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
            'index' => Pages\ListApiKeys::route('/'),
            'create' => Pages\CreateApiKey::route('/create'),
            'edit' => Pages\EditApiKey::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> */
    private static function permissionOptions(): array
    {
        return collect(ApiPermission::cases())
            ->mapWithKeys(fn (ApiPermission $permission): array => [$permission->value => $permission->label()])
            ->all();
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(ApiKeyStatus::cases())
            ->mapWithKeys(fn (ApiKeyStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    private static function formatPermissions(mixed $state): string
    {
        $values = collect(is_iterable($state) ? $state : [])
            ->map(fn (mixed $permission): string => $permission instanceof ApiPermission ? $permission->label() : (ApiPermission::tryFrom((string) $permission)?->label() ?? (string) $permission));

        return $values->implode('、');
    }
}
