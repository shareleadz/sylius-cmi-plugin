<?php

declare(strict_types=1);

namespace ShareLeadz\SyliusCmiPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class SyliusGatewayConfigurationType extends AbstractType
{
    public const ENABLED = 'enabled';
    public const DISABLED = 'disabled';
    public const ORDER_AFTER_PAY = 'order_after_pay';
    public const ORDER_SHOW = 'order_show';
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
            ->add('cmi_test_mode', ChoiceType::class, ['choices'=>['Enabled' => self::ENABLED, 'Disabled' => self::DISABLED]])
            ->add('cmi_auto_redirect', ChoiceType::class, ['choices'=>['Enabled' => self::ENABLED, 'Disabled' => self::DISABLED]])
            ->add('cmi_redirect_to', ChoiceType::class, ['choices'=>['Order after pay' => self::ORDER_AFTER_PAY, 'order show' => self::ORDER_SHOW]])
        ;
    }
}
