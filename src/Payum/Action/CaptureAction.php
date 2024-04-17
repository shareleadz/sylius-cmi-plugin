<?php

declare(strict_types=1);

namespace Leadz\SyliusCmiPlugin\Payum\Action;

use Leadz\SyliusCmiPlugin\Cmi\CmiHelper;
use Leadz\SyliusCmiPlugin\Form\Type\SyliusGatewayConfigurationType;
use Leadz\SyliusCmiPlugin\Payum\SyliusApi;
use Doctrine\Persistence\ManagerRegistry;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Reply\HttpPostRedirect;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Reply\HttpResponse;
use SM\Factory\FactoryInterface;
use SM\StateMachine\StateMachine;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Payum\Core\Request\Capture;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentInterface as ModelPaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;

final class CaptureAction implements ActionInterface, ApiAwareInterface
{
    private SyliusApi $api;
    private RequestStack $requestStack;
    private Router $router;
    private ManagerRegistry $managerRegistry;
    private FactoryInterface $factory;

    public function __construct(
        RequestStack               $requestStack,
        Router                     $router,
        ManagerRegistry            $managerRegistry,
        FactoryInterface           $factory,
    ) {
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->managerRegistry = $managerRegistry;
        $this->factory = $factory;
    }

    /**
     * @param Capture $request
     * @return void
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);
        /** @var PaymentInterface $payment */
        $payment = $request->getModel();
        /** @var Order $order */
        $order = $payment->getOrder();
        // check if the current request is a post request coming from CMI
        $httpRequest = $this->requestStack->getMainRequest();
        $postData = $httpRequest->request->all();
        if (count($postData) > 0) {
            $postData['storekey'] = $this->api->getCmiSecretKey();
            // sometimes at least on test gateway hashAlgorithm field contains empty space
            if (isset($postData['hashAlgorithm']) && $postData['hashAlgorithm'] !== null && (\preg_match('/\s/', $postData['hashAlgorithm']))) {
                $postData['hashAlgorithm'] = trim($postData['hashAlgorithm']);
            }
            $client = new CmiHelper($postData);
            $status = null;
            if ($client->validateHash($postData['HASH'])) {
                // check if payment was completed then redirect to product show page
                if (
                    (
                        $this->api->getUpdateStateBasedOn() === SyliusGatewayConfigurationType::UPDATE_STATE_BASED_ON_PAYMENT_STATE
                        && ModelPaymentInterface::STATE_COMPLETED === $payment->getState()
                    )
                    || (
                        $this->api->getUpdateStateBasedOn() === SyliusGatewayConfigurationType::UPDATE_STATE_BASED_ON_TEMP_FILE
                        && file_exists(sprintf('/tmp/order_%s_%s.tmp', ModelPaymentInterface::STATE_COMPLETED, $order->getId()))
                    )
                ) {
                    if ($this->api->getCmiRedirectTo() === SyliusGatewayConfigurationType::ORDER_SHOW) {
                        throw new HttpRedirect(
                            $this->router->generate(
                                'sylius_shop_order_show',
                                [
                                    '_locale' => $order->getLocaleCode(),
                                    'tokenValue' => $order->getTokenValue()
                                ],
                                UrlGeneratorInterface::ABSOLUTE_URL
                            ));
                    } else {
                        throw new HttpRedirect(
                            $this->router->generate(
                                'sylius_shop_order_thank_you',
                                [
                                    '_locale' => $order->getLocaleCode(),
                                ],
                                UrlGeneratorInterface::ABSOLUTE_URL
                            ));
                    }
                }
                if ($httpRequest->request->has('ProcReturnCode') && '00' == $httpRequest->request->get('ProcReturnCode')) {
                    $status = ModelPaymentInterface::STATE_COMPLETED;
                    $response = 'ACTION=POSTAUTH';
                } else {
                    $response = 'APPROVED';
                }
            } else {
                $status = ModelPaymentInterface::STATE_FAILED;
                $response = 'FAILURE';
            }
            if (null !== $status) {
                $payment->setDetails(array_merge($payment->getDetails(), ['status' => $status]));
                $getStatusRequest = new GetStatus($payment);
                $statusAction = new StatusAction();
                $statusAction->execute($getStatusRequest);
                $this->updatePaymentState($payment, $status);
                $this->managerRegistry->getManager()->flush();
                if ($this->api->getUpdateStateBasedOn() === SyliusGatewayConfigurationType::UPDATE_STATE_BASED_ON_TEMP_FILE) {
                    // create tmp file based on payment state using touch
                    touch(sprintf('/tmp/order_%s_%s.tmp', $status, $order->getId()));
                }
            }
            throw new HttpResponse($response);
        }
        $cmiHelper = new CmiHelper([
            'storekey' => $this->api->getCmiSecretKey(),
            'clientid' => $this->api->getCmiClientId(),
            'oid' => (string)$order->getId(),
            'amount' => $payment->getAmount() / 100,
            'shopurl' => $this->router->generate('sylius_shop_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'CallbackURL' => $request->getToken()->getTargetUrl(),
            'AutoRedirect' => $this->api->getCmiAutoRedirect() === SyliusGatewayConfigurationType::ENABLED ? 'true' : 'false',
            'okUrl' => $request->getToken()->getTargetUrl(),
            'failUrl' => $request->getToken()->getTargetUrl(),
            'BillToName' => (string)($order->getCustomer()->getFirstName() ?? $order->getCustomer()->getEmail()),
            //'BillToCompany' => $order->getCustomer()->getEmail(),
            'BillToStreet12' => $order->getBillingAddress()->getStreet(),
            'BillToCity' => $order->getBillingAddress()->getCity(),
            'BillToStateProv' => $order->getBillingAddress()->getProvinceName(),
            'BillToPostalCode' => $order->getBillingAddress()->getPostcode(),
            'BillToCountry' => '504',
            'tel' => $order->getCustomer()->getPhoneNumber(),
            'email' => $order->getCustomer()->getEmail(),
        ], $this->api->getCmiTestMode() === SyliusGatewayConfigurationType::ENABLED);

        throw new HttpPostRedirect($cmiHelper->getGateway(), $cmiHelper->getHttpPostRequestParameters());
    }

    private function updatePaymentState(PaymentInterface $payment, string $nextState): void
    {
        /** @var StateMachine $stateMachine */
        $stateMachine = $this->factory->get($payment, PaymentTransitions::GRAPH);

        if (null !== $transition = $stateMachine->getTransitionToState($nextState)) {
            $stateMachine->apply($transition);
        }
    }

    public function supports($request): bool
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof PaymentInterface;
    }

    public function setApi($api): void
    {
        if (!$api instanceof SyliusApi) {
            throw new UnsupportedApiException('Not supported. Expected an instance of ' . SyliusApi::class);
        }

        $this->api = $api;
    }
}
