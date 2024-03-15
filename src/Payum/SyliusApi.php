<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Payum;

final class SyliusApi
{
    private ?string $cmiClientId = null;
    private ?string $cmiSecretKey = null;
    private ?string $cmiUrl = null;

    public function __construct(?string $cmiClientId, ?string $cmiSecretKey, ?string $cmiUrl)
    {
        $this->cmiClientId = $cmiClientId;
        $this->cmiSecretKey = $cmiSecretKey;
        $this->cmiUrl = $cmiUrl;
    }

    public function getCmiClientId(): ?string
    {
        return $this->cmiClientId;
    }

    public function getCmiSecretKey(): ?string
    {
        return $this->cmiSecretKey;
    }

    public function getCmiUrl(): ?string
    {
        return $this->cmiUrl;
    }
}
