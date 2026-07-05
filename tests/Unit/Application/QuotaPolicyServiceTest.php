<?php

use App\Application\Api\QuotaPolicyService;
use App\Domain\Enums\PackageTier;

it('basic tier hard-blocks at quota', function () {
    $policy = app(QuotaPolicyService::class);

    expect($policy->shouldBlock(PackageTier::Basic, 9999, 10000))->toBeFalse()
        ->and($policy->shouldBlock(PackageTier::Basic, 10000, 10000))->toBeTrue()
        ->and($policy->shouldBlock(PackageTier::Basic, 10001, 10000))->toBeTrue();
});

it('professional tier soft-warns until 150% then globally hard-blocks', function () {
    $policy = app(QuotaPolicyService::class);

    // 超额但未达 150% → 软告警，不阻断
    expect($policy->shouldBlock(PackageTier::Professional, 10001, 10000))->toBeFalse()
        ->and($policy->shouldBlock(PackageTier::Professional, 14999, 10000))->toBeFalse()
        ->and($policy->isSoftWarning(PackageTier::Professional, 12000, 10000))->toBeTrue();

    // 150% 全局硬阻断
    expect($policy->shouldBlock(PackageTier::Professional, 15000, 10000))->toBeTrue()
        ->and($policy->isSoftWarning(PackageTier::Professional, 15000, 10000))->toBeFalse();
});

it('enterprise tier follows the same 150% global block as professional', function () {
    $policy = app(QuotaPolicyService::class);

    expect($policy->shouldBlock(PackageTier::Enterprise, 100000, 100000))->toBeFalse()
        ->and($policy->shouldBlock(PackageTier::Enterprise, 150000, 100000))->toBeTrue();
});

it('computes the 150% global hard-block threshold', function () {
    $policy = app(QuotaPolicyService::class);

    expect($policy->globalHardBlockThreshold(10000))->toBe(15000)
        ->and($policy->globalHardBlockThreshold(7))->toBe(11); // ceil(7*1.5)=11
});

it('does not flag soft warning for basic tier overage', function () {
    $policy = app(QuotaPolicyService::class);

    expect($policy->isSoftWarning(PackageTier::Basic, 9999, 10000))->toBeFalse();
});
