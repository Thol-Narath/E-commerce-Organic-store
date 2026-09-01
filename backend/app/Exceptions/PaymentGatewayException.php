<?php

namespace App\Exceptions;

use Exception;

/**
 * A failure to communicate with, or an unexpected response from, the ABA
 * PayWay gateway. Never exposes gateway internals to the client — the
 * client receives a generic message and the payment stays pending.
 */
class PaymentGatewayException extends Exception
{
    public function __construct(
        string $message,
        protected readonly ?string $gatewayCode = null,
    ) {
        parent::__construct($message);
    }

    public function gatewayCode(): ?string
    {
        return $this->gatewayCode;
    }
}