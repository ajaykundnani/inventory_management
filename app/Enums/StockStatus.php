<?php

namespace App\Enums;

/**
 * Derived stock classification; the three cases are mutually exclusive.
 */
enum StockStatus: string
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';
}
