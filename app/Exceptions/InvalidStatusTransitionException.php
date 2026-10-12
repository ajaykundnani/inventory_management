<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;

class InvalidStatusTransitionException extends BusinessRuleException
{
    public function __construct(OrderStatus $from, OrderStatus $to)
    {
        parent::__construct(
            "Cannot change order status from {$from->value} to {$to->value}.",
            ['status' => ["Transition from {$from->value} to {$to->value} is not allowed."]],
            422,
        );
    }
}
