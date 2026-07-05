<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * 商品上下架状态。租户域表 products.status。
 */
enum ProductStatus: string
{
    case Listed = 'listed';
    case Unlisted = 'unlisted';

    public function label(): string
    {
        return match ($this) {
            self::Listed => '上架',
            self::Unlisted => '下架',
        };
    }
}
