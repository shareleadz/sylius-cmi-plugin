<?php

declare(strict_types=1);

namespace Leadz\SyliusCmiPlugin\Payum\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\GetStatusInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

final class StatusAction implements ActionInterface
{
    public function __construct()
    {
    }

    /**
     * @param GetStatusInterface $request
     * @return void
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var SyliusPaymentInterface $payment */
        $payment = $request->getFirstModel();
        $details = $payment->getDetails();


        if ($payment instanceof SyliusPaymentInterface && isset($details['status'])) {
            if (PaymentInterface::STATE_COMPLETED === $details['status']) {
                $request->markCaptured();

                return;
            }

            if (PaymentInterface::STATE_FAILED === $details['status']) {
                $request->markFailed();

                return;
            }

            $request->markUnknown();
        }
    }

    public function supports($request): bool
    {
        return
            $request instanceof GetStatusInterface &&
            $request->getFirstModel() instanceof SyliusPaymentInterface
            ;
    }
}
