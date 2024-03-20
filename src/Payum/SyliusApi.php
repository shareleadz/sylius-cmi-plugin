<?php

declare(strict_types=1);

namespace Leadz\SyliusCmiPlugin\Payum;

final class SyliusApi
{
    private ?string $cmiClientId;
    private ?string $cmiSecretKey;
    private ?string $cmiTestMode;
    private ?string $cmiAutoRedirect;

    public function __construct(
        ?string $cmiClientId,
        ?string $cmiSecretKey,
        ?string $cmiTestMode,
        ?string $cmiAutoRedirect,
    ) {
        $this->cmiClientId = $cmiClientId;
        $this->cmiSecretKey = $cmiSecretKey;
        $this->cmiTestMode = $cmiTestMode;
        $this->cmiAutoRedirect = $cmiAutoRedirect;
    }

    public function getCmiClientId(): ?string
    {
        return $this->cmiClientId;
    }

    public function getCmiSecretKey(): ?string
    {
        return $this->cmiSecretKey;
    }

    public function getCmiTestMode(): ?string
    {
        return $this->cmiTestMode;
    }

    public function getCmiAutoRedirect(): ?string
    {
        return $this->cmiAutoRedirect;
    }
}
