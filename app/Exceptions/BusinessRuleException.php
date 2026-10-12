<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Expected domain failure that maps to a client error with optional field errors.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(string $message, protected array $errors = [], protected int $status = 422)
    {
        parent::__construct($message);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function status(): int
    {
        return $this->status;
    }
}
