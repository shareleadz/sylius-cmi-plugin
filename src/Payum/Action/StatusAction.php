<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Payum\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\GetStatusInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;

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
//        dump($request);
//        dump($payment);
//        die;

        $details = $payment->getDetails();


        if ($payment instanceof SyliusPaymentInterface) {
            if (200 === $details['status']) {
                $request->markCaptured();
//                dump('captured');

                return;
            }

            if (400 === $details['status']) {
                $request->markFailed();

                return;
            }

            $request->markUnknown();
        }
        /*else {
            $tokenDetails = $payment->getDetails();
            $payment = $this->paymentRepo->findOneBy(['id' => $tokenDetails->getId()]);
            $request->setModel($payment);
        }*/
    }

    public function supports($request): bool
    {
        return
            $request instanceof GetStatusInterface &&
            $request->getFirstModel() instanceof SyliusPaymentInterface
            ;
    }
}