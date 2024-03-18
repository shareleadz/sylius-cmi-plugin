<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Payum\Action;

use Acme\SyliusExamplePlugin\Payum\SyliusApi;
use Acme\SyliusExamplePlugin\Util\CmiPay;
use Acme\SyliusExamplePlugin\Util\CmiPaymentTool;
use CMI\CmiClient;
use Doctrine\Persistence\ManagerRegistry;
use GuzzleHttp\Client;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Reply\HttpPostRedirect;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\GetStatusInterface;
use Psr\Log\LoggerInterface;
use SM\Factory\FactoryInterface;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Payum\Core\Request\Capture;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;

final class CaptureAction implements ActionInterface, ApiAwareInterface
{
    /** @var Client */
    private $client;
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
        Client $client,
        LoggerInterface $logger,
        RequestStack $requestStack,
        Router $router,
        ManagerRegistry $managerRegistry,
        FactoryInterface $factory,
    ) {
        $this->client = $client;
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
//        $this->logger->info("##request##: ".json_encode($request->getToken()->));
//        dump($request);die;
//        dump($request->getToken()->getTargetUrl());die;

        RequestNotSupportedException::assertSupports($this, $request);
        /** @var SyliusPaymentInterface $payment */
        $payment = $request->getModel();
        //

        // check if a post request coming from CMI
        $postData = $this->requestStack->getMainRequest()->request->all();
        if (count($postData) > 0) {
            $postData['storekey'] = $this->api->getCmiSecretKey();
            if(isset($postData['hashAlgorithm']) && $postData['hashAlgorithm'] !== null && (\preg_match('/\s/', $postData['hashAlgorithm']))) {
                $postData['hashAlgorithm'] = trim($postData['hashAlgorithm']);
            }
            $client = new CmiClient($postData);
            $actualHash = $client->generateHash($this->api->getCmiSecretKey());
//            $dataHash = $postData['HASH'];
            $status = $client->hash_eq($postData['HASH']);
            $retrievedHash = $postData['HASH'];
//            dump([$actualHash, $retrievedHash]);die;
            //$actualHash;
            $this->logger->info(sprintf('#captured:status:%s#', $status));
            $this->logger->info(sprintf('#captured:ProcReturnCode:%s#', $this->requestStack->getMainRequest()->request->get('ProcReturnCode')));
            if ($status) {
                if ('00' == $this->requestStack->getMainRequest()->request->get('ProcReturnCode')) {
                    $payment->setDetails(array_merge($payment->getDetails(), ['status' => 200]));
//                    $request->setModel($payment);
                    $this->managerRegistry->getManager()->flush();
                    $getStatusRequest = new GetStatus($payment);
                    $statusAction = new StatusAction();
                    $statusAction->execute($getStatusRequest);
                    $this->logger->info(sprintf('#captured:isCaptured:%s#', $getStatusRequest->isCaptured()));
                    if ($getStatusRequest->isCaptured()) {
//                        $payment->setState(PaymentInterface::STATE_COMPLETED);
                        $this->updatePaymentState($payment, PaymentInterface::STATE_COMPLETED);
                        $this->managerRegistry->getManager()->flush();
                    }
//                    $payment->setDetails(['status' => 200]);
//                    $this->managerRegistry->getManager()->flush();

                    throw new HttpResponse('ACTION=POSTAUTH');
                } else {
                    throw new HttpResponse('APPROVED');
                }
            } else {
                throw new HttpResponse("FAILURE, \n$actualHash\n$retrievedHash\n".json_encode($postData));
            }
        }

        $client = new CmiClient([
            'storekey' => $this->api->getCmiSecretKey(), // STOREKEY
            'clientid' => $this->api->getCmiClientId(), // CLIENTID
            'oid' => (string)$payment->getOrder()->getId(), // COMMAND ID IT MUST BE UNIQUE
            'shopurl' => str_replace('http:', 'https:', $this->router->generate('sylius_shop_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL)),
            'okUrl' => $this->router->generate('sylius_shop_order_show', ['_locale' => $payment->getOrder()->getLocaleCode(), 'tokenValue' => $payment->getOrder()->getTokenValue()], UrlGeneratorInterface::ABSOLUTE_URL),
            'failUrl' => $this->router->generate('sylius_shop_order_show', ['_locale' => $payment->getOrder()->getLocaleCode(), 'tokenValue' => $payment->getOrder()->getTokenValue()], UrlGeneratorInterface::ABSOLUTE_URL),
            'email' => $payment->getOrder()->getCustomer()->getEmail(),
            'BillToName' => (string)($payment->getOrder()->getCustomer()->getFirstName() ?? $payment->getOrder()->getCustomer()->getEmail()),
            'BillToCompany' => $payment->getOrder()->getCustomer()->getEmail(),
            'BillToStreet12' => $payment->getOrder()->getBillingAddress()->getStreet(),
            'BillToCity' => $payment->getOrder()->getBillingAddress()->getCity(),
//            'BillToStateProv' => 'Maarif Casablanca',
            'BillToPostalCode' => $payment->getOrder()->getBillingAddress()->getPostcode(),
            'BillToCountry' => '504',
            'tel' => $payment->getOrder()->getCustomer()->getPhoneNumber(),
            'amount' => (string)$payment->getOrder()->getTotal(), // must be handled and secured
            'CallbackURL' => str_replace('http:', 'https:', $request->getToken()->getTargetUrl()),
            'AutoRedirect' => 'true',
        ]);

        $client->redirect_post();
//        throw new HttpPostRedirect(
//            $this->api->getCmiUrl(),
//        );
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
            $request->getModel() instanceof SyliusPaymentInterface;
    }

    public function setApi($api): void
    {
        if (!$api instanceof SyliusApi) {
            throw new UnsupportedApiException('Not supported. Expected an instance of ' . SyliusApi::class);
        }

        $this->api = $api;
    }
}
