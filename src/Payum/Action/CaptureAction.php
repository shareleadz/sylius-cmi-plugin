<?php

declare(strict_types=1);

namespace Leadz\SyliusCmiPlugin\Payum\Action;

use Leadz\SyliusCmiPlugin\Cmi\CmiProdClient;
use Leadz\SyliusCmiPlugin\Form\Type\SyliusGatewayConfigurationType;
use Leadz\SyliusCmiPlugin\Payum\SyliusApi;
use CMI\CmiClient;
use Doctrine\Persistence\ManagerRegistry;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Reply\HttpResponse;
use Psr\Log\LoggerInterface;
use SM\Factory\FactoryInterface;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Payum\Core\Request\Capture;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;

final class CaptureAction implements ActionInterface, ApiAwareInterface
{
    /** @var SyliusApi */
    private $api;
    /** @var LoggerInterface */
    private $logger;
    /** @var RequestStack */
    private $requestStack;
    /** @var Router */
    private $router;
    /** @var ManagerRegistry */
    private $managerRegistry;
    /** @var FactoryInterface */
    private $factory;

    public function __construct(
        LoggerInterface  $logger,
        RequestStack     $requestStack,
        Router           $router,
        ManagerRegistry  $managerRegistry,
        FactoryInterface $factory,
    )
    {
        $this->logger = $logger;
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
        $cmiClientClass = $this->api->getCmiTestMode() === SyliusGatewayConfigurationType::ENABLED ? CmiClient::class : CmiProdClient::class;
        // check if a post request coming from CMI
        $postData = $this->requestStack->getMainRequest()->request->all();
        if (count($postData) > 0) {
            $postData['storekey'] = $this->api->getCmiSecretKey();
            // sometimes at least on test gateway hashAlgorithm field contains empty space
            if (isset($postData['hashAlgorithm']) && $postData['hashAlgorithm'] !== null && (\preg_match('/\s/', $postData['hashAlgorithm']))) {
                $postData['hashAlgorithm'] = trim($postData['hashAlgorithm']);
            }
            $client = new $cmiClientClass($postData);
            $actualHash = $client->generateHash($this->api->getCmiSecretKey());
            $status = $client->hash_eq($postData['HASH']);
            $retrievedHash = $postData['HASH'];
            $this->logger->info(sprintf('#captured:status:%s#', $status));
            $this->logger->info(sprintf('#captured:ProcReturnCode:%s#', $this->requestStack->getMainRequest()->request->get('ProcReturnCode')));
            if ($status) {
                if ('00' == $this->requestStack->getMainRequest()->request->get('ProcReturnCode')) {
                    $payment->setDetails(array_merge($payment->getDetails(), ['status' => 200]));
                    $getStatusRequest = new GetStatus($payment);
                    $statusAction = new StatusAction();
                    $statusAction->execute($getStatusRequest);
                    $this->logger->info(sprintf('#captured:isCaptured:%s#', $getStatusRequest->isCaptured()));
                    if ($getStatusRequest->isCaptured()) {
                        $this->updatePaymentState($payment, PaymentInterface::STATE_COMPLETED);
                        $this->managerRegistry->getManager()->flush();
                    }

                    throw new HttpResponse('ACTION=POSTAUTH');
                } else {
                    throw new HttpResponse('APPROVED');
                }
            } else {
                throw new HttpResponse("FAILURE, \n$actualHash\n$retrievedHash\n" . json_encode($postData));
            }
        }
        $okUrl = $this->router->generate('sylius_shop_order_show', ['_locale' => $payment->getOrder()->getLocaleCode(), 'tokenValue' => $payment->getOrder()->getTokenValue()], UrlGeneratorInterface::ABSOLUTE_URL);
        if ($this->api->getCmiRedirectTo() === SyliusGatewayConfigurationType::ORDER_AFTER_PAY) {
            $okUrl = $this->router->generate('sylius_shop_order_after_pay', ['_locale' => $payment->getOrder()->getLocaleCode()], UrlGeneratorInterface::ABSOLUTE_URL);
        }
        $client = new $cmiClientClass([
            'storekey' => $this->api->getCmiSecretKey(),
            'clientid' => $this->api->getCmiClientId(),
            'oid' => (string)$payment->getOrder()->getId(),
            'shopurl' => $this->router->generate('sylius_shop_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'okUrl' => $okUrl,
            'failUrl' => $this->router->generate('sylius_shop_order_show', ['_locale' => $payment->getOrder()->getLocaleCode(), 'tokenValue' => $payment->getOrder()->getTokenValue()], UrlGeneratorInterface::ABSOLUTE_URL),
            'email' => $payment->getOrder()->getCustomer()->getEmail(),
            'BillToName' => (string)($payment->getOrder()->getCustomer()->getFirstName() ?? $payment->getOrder()->getCustomer()->getEmail()),
            //'BillToCompany' => $payment->getOrder()->getCustomer()->getEmail(),
            'BillToStreet12' => $payment->getOrder()->getBillingAddress()->getStreet(),
            'BillToCity' => $payment->getOrder()->getBillingAddress()->getCity(),
            'BillToStateProv' => $payment->getOrder()->getBillingAddress()->getProvinceName(),
            'BillToPostalCode' => $payment->getOrder()->getBillingAddress()->getPostcode(),
            'BillToCountry' => '504',
            'tel' => $payment->getOrder()->getCustomer()->getPhoneNumber(),
            'amount' => (string)$payment->getOrder()->getTotal(), // must be handled and secured
            'CallbackURL' => $request->getToken()->getTargetUrl(),
            'AutoRedirect' => $this->api->getCmiAutoRedirect() === SyliusGatewayConfigurationType::ENABLED ? 'true' : 'false',
        ]);

        $client->redirect_post();
    }

    private function updatePaymentState($payment, $nextState)
    {
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
