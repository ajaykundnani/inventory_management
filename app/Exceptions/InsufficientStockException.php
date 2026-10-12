<?php

namespace App\Exceptions;

class InsufficientStockException extends BusinessRuleException
{
    public function __construct(array $errors)
    {
        parent::__construct('Insufficient stock for one or more items.', $errors, 422);
    }
}
