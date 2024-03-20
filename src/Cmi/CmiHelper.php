<?php

namespace Leadz\SyliusCmiPlugin\Cmi;


class CmiHelper
{
    private string $gateway = 'https://testpayment.cmi.co.ma/fim/est3Dgate';
    private array $parameters;
    private ?string $hash;

    public function __construct(array $parameters = [], bool $testMode = true)
    {
        if (true !== $testMode) {
            $this->gateway = 'https://payment.cmi.co.ma/fim/est3Dgate';
        }

        $parameters = array_merge($this->getDefaultParameters(), $parameters);

        $storeKey = $parameters['storekey'];

        unset($parameters['storekey']);

        $this->parameters = $parameters;

        $this->hash = $this->generateHash($storeKey);
    }

    public function generateHash(string $storeKey): string
    {
        // amount|BillToCompany|BillToName|callbackUrl|clientid|currency|email|failUrl|hashAlgorithm|lang|okurl|rnd|storetype|TranType|storeKey
        $cmiParameters = $this->parameters;
        $postParameters = array_keys($cmiParameters);
        natcasesort($postParameters);
        $hashValue = '';
        foreach ($postParameters as $param) {
            $paramValue = trim($cmiParameters[$param]);
            $escapedParamValue = str_replace("|", "\\|", str_replace("\\", "\\\\", $paramValue));

            $lowerParam = strtolower($param);
            if ($lowerParam != "hash" && $lowerParam != "encoding") {
                $hashValue = $hashValue . $escapedParamValue . "|";
            }
        }
        $escapedStoreKey = str_replace("|", "\\|", str_replace("\\", "\\\\", $storeKey));
        $hashValue = $hashValue . $escapedStoreKey;

        $calculatedHashValue = hash('sha512', $hashValue);

        return base64_encode(pack('H*', $calculatedHashValue));
    }

    public function getHttpPostRequestParameters(): array
    {
        return array_merge($this->getParameters(), ['HASH' => $this->getHash()]);
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function validateHash(string $hash): bool
    {
        return $this->hash === $hash;
    }

    public function getDefaultParameters(): array
    {
        return [
            'storetype' => '3D_PAY_HOSTING',
            'trantype' => 'PreAuth',
            'currency' => '504', // MAD
            'rnd' => microtime(),
            'lang' => 'fr',
            'hashAlgorithm' => 'ver3',
            'encoding' => 'UTF-8',
            'refreshtime' => '5'
        ];
    }

    public function getGateway(): string
    {
        return $this->gateway;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }
}
