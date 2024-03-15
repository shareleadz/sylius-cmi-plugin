<?php

namespace Acme\SyliusExamplePlugin\Util;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;

class CmiPaymentTool
{
    public function __construct(private readonly string $cmiSecretKey)
    {
    }

    public function convertData(CmiPay $params): array
    {
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [new GetSetMethodNormalizer()];
        $serializer = new Serializer($normalizers, $encoders);
        $jsonContent = $serializer->serialize($params, 'json');

        $data = (array) json_decode($jsonContent);

        foreach ($data as $key => $value) {
            $data[$key] = trim(html_entity_decode($value));
        }

        return $data;
    }

    public function hashValue(array $data): string
    {
        $params = new CmiPay();
        $params->setSecretKey($this->cmiSecretKey);
        $storeKey = $params->getSecretKey();
        $data = $this->unsetData($data);
        $postParams = [];
        foreach ($data as $key => $value) {
            $postParams[] = $key;
        }
        natcasesort($postParams);

        $hashval = '';
        foreach ($postParams as $param) {
            $paramValue = trim(html_entity_decode(preg_replace("/\n$/", '', $data[$param]), ENT_QUOTES, 'UTF-8'));
            $escapedParamValue = str_replace(['\\', '|'], ['\\\\', '\\|'], $paramValue);
            $escapedParamValue = preg_replace('/document(.)/i', 'document.', $escapedParamValue);

            $lowerParam = strtolower($param);
            if ('hash' !== $lowerParam && 'encoding' !== $lowerParam) {
                $hashval .= $escapedParamValue.'|';
            }
        }

        $escapedStoreKey = str_replace(['\\', '|'], ['\\\\', '\\|'], $storeKey);
        $hashval .= $escapedStoreKey;

        $calculatedHashValue = hash('sha512', $hashval);

        return base64_encode(pack('H*', $calculatedHashValue));
    }

    public function unsetData(array $data): array
    {
        unset($data['gatewayurl'], $data['secretKey'], /*$data['ProcReturnCode']*/);

        return $data;
    }
}
