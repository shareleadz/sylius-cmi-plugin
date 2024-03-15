<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class SyliusGatewayConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /*
            CMI_CLIENT_ID=600001825
            CMI_URL="https://testpayment.cmi.co.ma/fim/est3Dgate"
            CMI_SECRET_KEY="Ocarz2020!"
         */
        $builder
            ->add('cmi_client_id', TextType::class)
            ->add('cmi_secret_key', TextType::class)
            ->add('cmi_url', TextType::class)
        ;
    }
}
