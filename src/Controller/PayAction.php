<?php

namespace Acme\SyliusExamplePlugin\Controller;

use Acme\SyliusExamplePlugin\Util\CmiPay;
use Acme\SyliusExamplePlugin\Util\CmiPaymentTool;
use Psr\Log\LoggerInterface;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
class PayAction extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CmiPaymentTool $cmiPaymentTool,
        private readonly string $cmiClientId,
        private readonly string $cmiUrl,
        private readonly string $appBaseUrl,
        private readonly OrderRepository $orderRepository,
    ) {
    }

    #[Route(path: '/orders/{id}/payment_request', name: 'pay_order')]
    public function __invoke(?int $id, Request $request): Response
    {
        $order = $this->orderRepository->find($id);
        if (null === $order) {
            throw new BadRequestException('Not Found');
        }
        $this->logger->info('cmi pay: order: '.$order->getId());
//        if (OrderStatus::Paid === $order->getStatus() || $order->getTotal() <= 0) {
//            return $this->redirect($this->appBaseUrl.'/order-purchase?orderId='.$order->getId().'&step=3');
//        }
//        $user = $this->userRepository->findOneBy(['email' => $this->security->getUser()?->getUserIdentifier()]);
//        if (null === $user) {
//            throw new BadRequestException('Unauthorized');
//        }
//        if ($user->getId() !== $order->getUser()?->getId()) {
//            throw new BadRequestException('Unauthorized');
//        }
//        $bearer = $request->query->get('bearer');
        $params = new CmiPay();
        // Setup new payment parameters
        $okUrl = str_replace('http:', 'https:', $this->generateUrl('cmi_payment_ok', ['id' => $order->getId()], UrlGeneratorInterface::ABSOLUTE_URL));
        $failUrl = str_replace('http:', 'https:', $this->generateUrl('cmi_payment_fail', ['id' => $order->getId()], UrlGeneratorInterface::ABSOLUTE_URL));
        $callbackUrl = str_replace('http:', 'https:', $this->generateUrl('cmi_payment_callback', ['id' => $order->getId()], UrlGeneratorInterface::ABSOLUTE_URL));
        $rnd = microtime();
        $params->setGatewayurl($this->cmiUrl)
            ->setclientid($this->cmiClientId)
            ->setTel($user->getPhone())
            ->setEmail($user->getEmail())
            ->setBillToName($user->getFullName() ?? $user->getEmail())
            ->setBillToCompany($user->getCompany() ?? $user->getEmail())
            ->setBillToStreet1($user->getAddress())
            //->setBillToStateProv('BillToStateProv')
            ->setBillToPostalCode($user->getZipCode())
            ->setBillToCity($user->getCity()?->getName())
            ->setBillToCountry('MA')
            ->setOid($order->getId())
            ->setCurrency('504') // ISO code for MAD: 504
            ->setAmount($order->getTotal()) // one credit = 180dh
            ->setOkUrl($okUrl)
            ->setCallbackUrl($callbackUrl)
            ->setFailUrl($failUrl)
            ->setShopurl($this->appBaseUrl)
            ->setEncoding('UTF-8')
            ->setStoretype('3D_PAY_HOSTING')
            ->setHashAlgorithm('ver3')
            ->setTranType('PreAuth')
            ->setRefreshtime('5')
            ->setLang('fr')
            ->setRnd($rnd)
        ;
        $submittedData = $this->cmiPaymentTool->convertData($params);
        $hash = $this->cmiPaymentTool->hashValue($submittedData);
        $submittedData['HASH'] = $hash;
        $submittedData = $this->cmiPaymentTool->unsetData($submittedData);

        return $this->render('payment/request.html.twig', [
            'data' => $submittedData,
            'url' => $params->getGatewayurl(),
        ]);
    }
}
