<?php

declare(strict_types=1);

namespace Leadz\SyliusCmiPlugin\Payum;

final class SyliusApi
{
    private ?string $cmiClientId;
    private ?string $cmiSecretKey;
    private ?string $cmiTestMode;
    private ?string $cmiAutoRedirect;
    private ?string $cmiRedirectTo;
    private ?string $updateStateBasedOn;

    public function __construct(
        ?string $cmiClientId,
        ?string $cmiSecretKey,
        ?string $cmiTestMode,
        ?string $cmiAutoRedirect,
        ?string $cmiRedirectTo,
        ?string $updateStateBasedOn
    ) {
        $this->cmiClientId = $cmiClientId;
        $this->cmiSecretKey = $cmiSecretKey;
        $this->cmiTestMode = $cmiTestMode;
        $this->cmiAutoRedirect = $cmiAutoRedirect;
        $this->cmiRedirectTo = $cmiRedirectTo;
        $this->updateStateBasedOn = $updateStateBasedOn;
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

    public function getCmiRedirectTo(): ?string
    {
        return $this->cmiRedirectTo;
    }

    public function getUpdateStateBasedOn(): ?string
    {
        return $this->updateStateBasedOn;
    }
}
