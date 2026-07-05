<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * 开放 API 权限点（api_keys.permissions JSON 数组元素）。
 *
 * 开放 API 网关按 AccessToken 所属密钥的权限集合鉴权（阶段 4）。
 */
enum ApiPermission: string
{
    case ProductQuery = 'product_query';
    case OrderManage = 'order_manage';
    case DashboardRead = 'dashboard_read';
    case BillQuery = 'bill_query';

    public function label(): string
    {
        return match ($this) {
            self::ProductQuery => '商品查询',
            self::OrderManage => '订单管理',
            self::DashboardRead => '看板读取',
            self::BillQuery => '账单查询',
        };
    }
}
