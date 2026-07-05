<?php

declare(strict_types=1);

namespace App\Filament\Merchant\Pages;

use App\Domain\Enums\BillStatus;
use App\Domain\Enums\CouponStatus;
use App\Domain\Enums\OrderStatus;
use App\Models\Billing\TenantBill;
use App\Models\Marketing\Coupon;
use App\Models\Order\Order;
use App\Models\Product\Product;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class MerchantDashboardPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = '经营管理';

    protected static ?string $navigationLabel = '店铺概览';

    protected static ?string $title = '店铺概览';

    protected static ?string $slug = 'overview';

    protected static string $view = 'filament.merchant.pages.merchant-dashboard-page';

    public function getProductCount(): int
    {
        return Product::query()->count();
    }

    public function getPendingOrderCount(): int
    {
        return Order::query()->where('status', OrderStatus::PendingPayment)->count();
    }

    public function getActiveCouponCount(): int
    {
        return Coupon::query()->where('status', CouponStatus::Active)->count();
    }

    public function getPendingBillCount(): int
    {
        return TenantBill::query()->where('status', BillStatus::PendingSettlement)->count();
    }

    public function getRevenueTotal(): string
    {
        return number_format((float) Order::query()->sum('total_amount'), 2);
    }

    /** @return Collection<int, Order> */
    public function getRecentOrders(): Collection
    {
        return Order::query()
            ->latest()
            ->limit(5)
            ->get();
    }
}
