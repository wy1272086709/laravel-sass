<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">商品数</div>
            <div class="mt-2 text-2xl font-semibold">{{ $this->getProductCount() }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">待付款订单</div>
            <div class="mt-2 text-2xl font-semibold">{{ $this->getPendingOrderCount() }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">进行中优惠</div>
            <div class="mt-2 text-2xl font-semibold">{{ $this->getActiveCouponCount() }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">待结算账单</div>
            <div class="mt-2 text-2xl font-semibold">{{ $this->getPendingBillCount() }}</div>
        </x-filament::section>
    </div>

    <div class="mt-6 grid gap-4 xl:grid-cols-3">
        <x-filament::section class="xl:col-span-1">
            <div class="text-sm text-gray-500 dark:text-gray-400">累计交易额</div>
            <div class="mt-2 text-3xl font-semibold">¥{{ $this->getRevenueTotal() }}</div>
        </x-filament::section>

        <x-filament::section class="xl:col-span-2">
            <x-slot name="heading">最近订单</x-slot>

            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($this->getRecentOrders() as $order)
                    <div class="flex items-center justify-between gap-4 py-3">
                        <div>
                            <div class="font-medium">{{ $order->order_no }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $order->buyer_name }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-medium">¥{{ number_format((float) $order->total_amount, 2) }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $order->status->label() }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-sm text-gray-500 dark:text-gray-400">暂无订单</div>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
