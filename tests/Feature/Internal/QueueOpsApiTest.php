<?php

use App\Domain\Enums\QueueJobStatus;
use App\Infrastructure\Redis\DeadLetterQueue;
use App\Infrastructure\Redis\DelayQueue;
use App\Infrastructure\Redis\KeyResolver;
use App\Models\Platform\PlatformUser;
use App\Models\System\QueueJobLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

beforeEach(function () {
    Redis::del(KeyResolver::delayQueue('close_expired_orders'));
    Redis::del(KeyResolver::deadLetterQueue('default'));
    Redis::del(KeyResolver::deadLetterQueue('billing'));
});

afterEach(function () {
    Redis::del(KeyResolver::delayQueue('close_expired_orders'));
    Redis::del(KeyResolver::deadLetterQueue('default'));
    Redis::del(KeyResolver::deadLetterQueue('billing'));
});

it('returns queue operation summary from real data sources', function () {
    $user = PlatformUser::factory()->create();
    QueueJobLog::factory()->create(['status' => QueueJobStatus::Success]);
    QueueJobLog::factory()->create(['status' => QueueJobStatus::Failed]);
    app(DelayQueue::class)->push('close_expired_orders', ['order_id' => 1], 3600);
    app(DeadLetterQueue::class)->push('default', ['job' => 'Example'], 'boom');

    $this->actingAs($user, 'platform')
        ->getJson('/internal/queue-ops/summary')
        ->assertOk()
        ->assertJsonPath('data.jobs.success', 1)
        ->assertJsonPath('data.jobs.failed', 1)
        ->assertJsonPath('data.delayed.close_expired_orders', 1)
        ->assertJsonPath('data.dead_letters.default', 1);
});

it('lists queue job logs and dead letters', function () {
    $user = PlatformUser::factory()->create();
    QueueJobLog::factory()->create([
        'name' => 'MonthlyBillingJob',
        'queue' => 'billing',
        'status' => QueueJobStatus::Success,
    ]);
    app(DeadLetterQueue::class)->push('billing', ['job' => 'MonthlyBillingJob'], 'failed');

    $this->actingAs($user, 'platform')
        ->getJson('/internal/queue-ops/jobs?queue=billing')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'MonthlyBillingJob');

    $this->actingAs($user, 'platform')
        ->getJson('/internal/queue-ops/dead-letters?queue=billing')
        ->assertOk()
        ->assertJsonPath('data.0.reason', 'failed');
});

it('requires platform authentication', function () {
    $this->getJson('/internal/queue-ops/summary')->assertUnauthorized();
});
