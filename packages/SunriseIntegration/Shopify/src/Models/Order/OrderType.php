<?php

namespace SunriseIntegration\Shopify\Models\Order;

class OrderType
{
    const SALES_ORDER = 0;
    const WHOLESALE_ORDER = 1;

    public static function get($orderTypeId)
    {
        $orderTypes = [
            self::SALES_ORDER => 'Sales',
            self::WHOLESALE_ORDER => 'Wholesale'
        ];

        return $orderTypes[$orderTypeId] ?? 'Sales';
    }

    public static function getPerformanceTeamType($orderTypeId)
    {
        $orderTypes = [
            self::SALES_ORDER => null,
            self::WHOLESALE_ORDER => 'Wholesale'
        ];

        return $orderTypes[$orderTypeId] ?? null;
    }
}
