<?php

use App\Domain\Enums\ApiKeyStatus;
use App\Domain\Enums\ApiPermission;
use App\Domain\Enums\BillStatus;
use App\Domain\Enums\CouponStatus;
use App\Domain\Enums\CouponType;
use App\Domain\Enums\LoginResult;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PackageChangeType;
use App\Domain\Enums\PackageTier;
use App\Domain\Enums\ProductStatus;
use App\Domain\Enums\QueueJobStatus;
use App\Domain\Enums\ReconciliationStatus;
use App\Domain\Enums\RiskAlertStatus;
use App\Domain\Enums\RiskAlertType;
use App\Domain\Enums\RiskLevel;
use App\Domain\Enums\TenantStatus;

it('has the correct backed values per SDD §3.1', function () {
    $values = static fn ($cases) => array_map(static fn ($case) => $case->value, $cases);

    expect($values(TenantStatus::cases()))->toBe(['enabled', 'disabled']);
    expect($values(ProductStatus::cases()))->toBe(['listed', 'unlisted']);
    expect($values(OrderStatus::cases()))->toBe([
        'pending_payment', 'paid', 'shipped', 'completed', 'refund_requested', 'cancelled',
    ]);
    expect($values(CouponType::cases()))->toBe(['full_reduction', 'discount']);
    expect($values(CouponStatus::cases()))->toBe(['not_started', 'active', 'ended']);
    expect($values(ApiKeyStatus::cases()))->toBe(['enabled', 'disabled']);
    expect($values(ApiPermission::cases()))->toBe([
        'product_query', 'order_manage', 'dashboard_read', 'bill_query',
    ]);
    expect($values(BillStatus::cases()))->toBe(['pending_settlement', 'settled', 'overdue']);
    expect($values(RiskLevel::cases()))->toBe(['high', 'medium', 'low']);
    expect($values(RiskAlertType::cases()))->toBe([
        'brush_order', 'duplicate_payment', 'abnormal_login', 'high_refund_rate',
    ]);
    expect($values(RiskAlertStatus::cases()))->toBe(['pending', 'handled', 'ignored']);
    expect($values(ReconciliationStatus::cases()))->toBe(['unreconciled', 'reconciled']);
    expect($values(PackageChangeType::cases()))->toBe(['new_purchase', 'upgrade', 'downgrade']);
    expect($values(QueueJobStatus::cases()))->toBe(['pending', 'processing', 'success', 'failed', 'dead']);
    expect($values(LoginResult::cases()))->toBe(['success', 'failure']);
    expect($values(PackageTier::cases()))->toBe(['basic', 'professional', 'enterprise']);
});

it('allows legal order transitions and rejects illegal ones (SDD §3.3)', function () {
    // 合法迁移
    expect(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Paid))->toBeTrue();
    expect(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Cancelled))->toBeTrue();
    expect(OrderStatus::Paid->canTransitionTo(OrderStatus::Shipped))->toBeTrue();
    expect(OrderStatus::Paid->canTransitionTo(OrderStatus::RefundRequested))->toBeTrue();
    expect(OrderStatus::Shipped->canTransitionTo(OrderStatus::Completed))->toBeTrue();
    expect(OrderStatus::RefundRequested->canTransitionTo(OrderStatus::Completed))->toBeTrue();
    expect(OrderStatus::RefundRequested->canTransitionTo(OrderStatus::Cancelled))->toBeTrue();

    // 非法迁移
    expect(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Shipped))->toBeFalse();
    expect(OrderStatus::Shipped->canTransitionTo(OrderStatus::Paid))->toBeFalse();
    expect(OrderStatus::Completed->canTransitionTo(OrderStatus::Cancelled))->toBeFalse();
    expect(OrderStatus::Cancelled->canTransitionTo(OrderStatus::Paid))->toBeFalse();
});

it('exposes package tier quota policy helpers', function () {
    expect(PackageTier::Basic->hardBlockOnOverage())->toBeTrue();
    expect(PackageTier::Professional->hardBlockOnOverage())->toBeFalse();
    expect(PackageTier::Enterprise->hardBlockOnOverage())->toBeFalse();

    expect(PackageTier::Basic->allowsOverageBilling())->toBeFalse();
    expect(PackageTier::Professional->allowsOverageBilling())->toBeTrue();
    expect(PackageTier::Enterprise->allowsOverageBilling())->toBeTrue();
});

it('renders chinese labels', function () {
    expect(PackageTier::Basic->label())->toBe('基础版');
    expect(OrderStatus::PendingPayment->label())->toBe('待付款');
    expect(BillStatus::PendingSettlement->label())->toBe('待结算');
});
