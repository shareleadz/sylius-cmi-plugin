<?php

declare(strict_types=1);

namespace Leadz\SyliusCmiPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class SyliusGatewayConfigurationType extends AbstractType
{
    public const ENABLED = 'enabled';
    public const DISABLED = 'disabled';
    public const ORDER_THANK_YOU = 'order_thank_you';
    public const ORDER_SHOW = 'order_show';
    public const UPDATE_STATE_BASED_ON_PAYMENT_STATE = 'update_state_based_on_payment_state';
    public const UPDATE_STATE_BASED_ON_TEMP_FILE = 'update_state_based_on_temp_file';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cmi_client_id', TextType::class)
            ->add('cmi_secret_key', TextType::class)
            ->add('cmi_test_mode', ChoiceType::class, [
                'choices' => [
                    'Enabled' => self::ENABLED,
                    'Disabled' => self::DISABLED
                ]
            ])
            ->add('cmi_auto_redirect', ChoiceType::class, [
                'choices' => [
                    'Enabled' => self::ENABLED,
                    'Disabled' => self::DISABLED
                ]
            ])
            ->add('cmi_redirect_to', ChoiceType::class, [
                'choices' => [
                    'Order thank you' => self::ORDER_THANK_YOU,
                    'Order show' => self::ORDER_SHOW
                ]
            ])
            ->add('update_state_based_on', ChoiceType::class, [
                'choices' => [
                    'Payment state' => self::UPDATE_STATE_BASED_ON_PAYMENT_STATE,
                    'Temp file' => self::UPDATE_STATE_BASED_ON_TEMP_FILE
                ],
                'help' => 'Choose how to update the state of the order based on the payment state. (use temp file for slow database updates)'
            ])
        ;
    }
}
