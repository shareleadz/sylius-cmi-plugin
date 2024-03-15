<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Payum\Action;

use Acme\SyliusExamplePlugin\Payum\SyliusApi;
use Acme\SyliusExamplePlugin\Util\CmiPay;
use Acme\SyliusExamplePlugin\Util\CmiPaymentTool;
use GuzzleHttp\Client;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Reply\HttpPostRedirect;
use Payum\Core\Reply\HttpResponse;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Payum\Core\Request\Capture;
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

    public function __construct(Client $client, LoggerInterface $logger, RequestStack $requestStack, Router $router)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->router = $router;
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
        $cmiPaymentTool = new CmiPaymentTool($this->api->getCmiSecretKey());

        // check if a post request coming from CMI
        $postData = $this->requestStack->getMainRequest()->request->all();
        if (count($postData) > 0) {
            if(isset($postData['hashAlgorithm']) && $postData['hashAlgorithm'] !== null && (\preg_match('/\s/', $postData['hashAlgorithm']))) {
                $postData['hashAlgorithm'] = trim($postData['hashAlgorithm']);
            }
            $this->logger->info("##request_callback_received_hash##: ".$postData['HASH']);
            $actualHash = $cmiPaymentTool->hashValue($postData);
            $this->logger->info("##request_callback_actual_hash##: ".$actualHash);
            $retrievedHash = $postData['HASH'];
//            dump([$actualHash, $retrievedHash]);die;
            if ($retrievedHash === $actualHash) {
                if ('00' === $this->requestStack->getMainRequest()->request->get('ProcReturnCode')) {
                    throw new HttpResponse('ACTION=POSTAUTH');
                } else {
                    throw new HttpResponse('APPROVED');
                }
            } else {
                throw new HttpResponse("FAILURE, \n$actualHash\n$retrievedHash\n".json_encode($postData));
            }
        }

        $params = new CmiPay();
        $rnd = microtime();
        $params->setGatewayurl('https://testpayment.cmi.co.ma/fim/est3Dgate')
            ->setclientid($this->api->getCmiClientId())
            ->setTel($payment->getOrder()->getCustomer()->getPhoneNumber())
            ->setEmail($payment->getOrder()->getCustomer()->getEmail())
            ->setBillToName($payment->getOrder()->getCustomer()->getFullName() ?? $payment->getOrder()->getCustomer()->getEmail())
            ->setBillToCompany($payment->getOrder()->getCustomer()->getEmail())
            ->setBillToStreet1($payment->getOrder()->getBillingAddress()->getStreet())
            //->setBillToStateProv('BillToStateProv')
            ->setBillToPostalCode($payment->getOrder()->getBillingAddress()->getPostcode())
            ->setBillToCity($payment->getOrder()->getBillingAddress()->getCity())
            ->setBillToCountry('MA')
            ->setOid($payment->getOrder()->getId())
            ->setCurrency('504') // ISO code for MAD: 504
            ->setAmount($payment->getOrder()->getTotal()) // one credit = 180dh
            ->setOkUrl($request->getToken()->getAfterUrl())
            ->setCallbackUrl($request->getToken()->getTargetUrl())
            ->setFailUrl($request->getToken()->getTargetUrl())
            ->setShopurl(str_replace('http:', 'https:', $this->router->generate('sylius_shop_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL)))
            ->setEncoding('UTF-8')
            ->setStoretype('3D_PAY_HOSTING')
            ->setHashAlgorithm('ver3')
            ->setTranType('PreAuth')
            ->setRefreshtime('5')
            ->setLang('fr')
            ->setRnd($rnd);

        $submittedData = $cmiPaymentTool->convertData($params);
        $hash = $cmiPaymentTool->hashValue($submittedData);
        $submittedData['HASH'] = $hash;
        $submittedData = $cmiPaymentTool->unsetData($submittedData);
//dump($submittedData);die;
//        return $this->render('payment/request.html.twig', [
//            'data' => $submittedData,
//            'url' => $params->getGatewayurl(),
//        ]);
//        dump($submittedData);die;
        throw new HttpPostRedirect(
            $this->api->getCmiUrl(),
            $submittedData,
        );

//        try {
////            $response = $this->client->request('POST', 'https://sylius-payment.free.beeceptor.com', [
////                'body' => json_encode([
////                    'price' => $payment->getAmount(),
////                    'currency' => $payment->getCurrencyCode(),
////                    'cmiSecretKey' => $this->api->getCmiSecretKey(),
////                ]),
////            ]);
//        } catch (RequestException $exception) {
//            $response = $exception->getResponse();
//        } finally {
//            $payment->setDetails(['status' => $response->getStatusCode()]);
//        }
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
