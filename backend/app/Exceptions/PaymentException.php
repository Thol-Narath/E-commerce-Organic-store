<?php

namespace App\Exceptions;

use Exception;

/**
 * A payment operation rejected because of the business rules of the store
 * (e.g. order not found, method disabled, order already paid). Carries the
 * HTTP status that should be returned to the client.
 */
class PaymentException extends Exception
{
    public function __construct(string $message, protected int $responseStatus = 422)
    {
        parent::__construct($message);
    }

    public function responseStatus(): int
    {
        return $this->responseStatus;
    }
}