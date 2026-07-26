<?php

use App\Domain\Enums\OperationAction;
use App\Models\System\OperationLog;
use App\Models\Tenant\Tenant;
use App\Support\OperationLogContext;
use App\Support\OperationLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('maps the api_key actor kind to the api_key_id column', function () {
    $tenant = Tenant::factory()->create();

    app(OperationLogger::class)->log(new OperationLogContext(
        tenantId: $tenant->id,
        actorKind: 'api_key',
        actorId: 99,
        actorLabel: 'ApiKey:99',
        subjectType: 'order',
        subjectId: 123,
        subjectLabel: 'ORD20260726X',
        action: OperationAction::Created,
        toStatus: 'pending_payment',
        payload: ['items_count' => 2],
        idempotencyKey: 'idem-1',
        ipAddress: '127.0.0.1',
        userAgent: 'PHPUnit',
    ));

    $row = OperationLog::query()->withoutGlobalScopes()->first();

    expect($row)->not->toBeNull()
        ->and($row->tenant_id)->toBe($tenant->id)
        ->and($row->actor_kind)->toBe('api_key')
        ->and($row->api_key_id)->toBe(99)
        ->and($row->merchant_user_id)->toBeNull()
        ->and($row->platform_user_id)->toBeNull()
        ->and($row->action)->toBe(OperationAction::Created)
        ->and($row->action->label())->toBe('创建')
        ->and($row->payload)->toBe(['items_count' => 2])
        ->and($row->idempotency_key)->toBe('idem-1');
});

it('writes a system actor with all foreign keys null', function () {
    $tenant = Tenant::factory()->create();

    app(OperationLogger::class)->log(new OperationLogContext(
        tenantId: $tenant->id,
        actorKind: 'system',
        actorId: null,
        actorLabel: '系统超时关单',
        subjectType: 'order',
        subjectId: 1,
        subjectLabel: 'ORD1',
        action: OperationAction::Cancelled,
    ));

    $row = OperationLog::query()->withoutGlobalScopes()->first();

    expect($row->actor_kind)->toBe('system')
        ->and($row->api_key_id)->toBeNull()
        ->and($row->merchant_user_id)->toBeNull()
        ->and($row->platform_user_id)->toBeNull()
        ->and($row->actor_label)->toBe('系统超时关单');
});

it('swallows logging exceptions instead of propagating them', function () {
    // 用 Eloquent creating 事件注入异常，环境无关地验证 best-effort 吞异常
    OperationLog::creating(fn () => throw new RuntimeException('boom'));

    $result = app(OperationLogger::class)->log(new OperationLogContext(
        tenantId: Tenant::factory()->create()->id,
        actorKind: 'system',
        actorId: null,
        actorLabel: '系统',
        subjectType: 'order',
        subjectId: null,
        subjectLabel: null,
        action: OperationAction::Updated,
    ));

    expect($result)->toBeNull()
        ->and(OperationLog::query()->withoutGlobalScopes()->count())->toBe(0);
});
