<?php

namespace App\Services\Vms;

use RuntimeException;

class ProxmoxException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $status = 0,
        private ?string $responseBody = null,
    ) {
        parent::__construct($message, $status);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function responseBody(): ?string
    {
        return $this->responseBody;
    }
}
