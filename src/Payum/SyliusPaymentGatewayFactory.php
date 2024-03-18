<?php

declare(strict_types=1);

namespace ShareLeadz\SyliusCmiPlugin\Payum;

use ShareLeadz\SyliusCmiPlugin\Payum\Action\StatusAction;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

final class SyliusPaymentGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => 'cmi_payment',
            'payum.factory_title' => 'CMI Payment',
            'payum.action.status' => new StatusAction(),
        ]);

        $config['payum.api'] = function (ArrayObject $config) {
            return new SyliusApi(
                $config['cmi_client_id'],
                $config['cmi_secret_key'],
                $config['cmi_test_mode'],
                $config['cmi_auto_redirect'],
                $config['cmi_redirect_to'],
            );
        };
    }
}
