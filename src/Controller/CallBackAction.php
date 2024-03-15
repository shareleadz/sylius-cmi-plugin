<?php

namespace Acme\SyliusExamplePlugin\Controller;

use App\Enum\CampaignStatus;
use App\Enum\OrderStatus;
use App\Repository\CreditRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Util\CmiPaymentTool;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class CallBackAction extends AbstractController
{
    public function __construct(
        private readonly CmiPaymentTool $cmiPaymentTool,
        private readonly OrderRepository $orderRepository,
        private readonly LoggerInterface $logger,
        private readonly CreditRepository $creditRepository,
        private readonly UserRepository $userRepository,
        private readonly ManagerRegistry $managerRegistry,
        private readonly Security $security,
        private readonly MailerInterface                    $mailer,
    ) {
    }

    #[Route(path: '/orders/{id}/payment_callback
    ', name: 'cmi_payment_callback')]
    public function __invoke(int $id, Request $request): Response
    {
        $order = $this->orderRepository->find($id);
        if (null === $order) {
            $this->logger->info(sprintf('cmi callback received could not find order with id: %d', $id));
            throw new BadRequestException('Not Found');
        }
        $user = $this->userRepository->findOneBy(['email' => $this->security->getUser()?->getUserIdentifier()]);
        if (!$user || $user->getId() !== $order->getUser()?->getId()) {
            $this->logger->info(sprintf('cmi callback order: %d, user not valid %s', $order->getId(), $this->security->getUser()?->getUserIdentifier()));
            throw new BadRequestException('Unauthorized');
        }
        $response = null;
        try {
            $postData = $request->request->all();
            if (count($postData) > 0) {
                $actualHash = $this->cmiPaymentTool->hashValue($postData);
                $retrievedHash = $postData['HASH'];

                if ($retrievedHash === $actualHash) {
                    if ('00' === $request->request->get('ProcReturnCode')) {
                        $response = 'ACTION=POSTAUTH';
                        $order->setStatus(OrderStatus::Paid);

                        $email = (new TemplatedEmail())
                            ->from('system@ocarz.ma')
                            ->bcc($order->getProduct()->getOwner()->getEmail())
                            ->subject('Payment Confirmation - Order #' . $order->getId())
                            ->htmlTemplate('emails/orderPaymentConfirmation.html.twig')
                            ->context([
                                'ownerFirstName' => $order?->getProduct()?->getOwner()?->getFirstName(),
                                'ownerLastName' => $order?->getProduct()?->getOwner()?->getLastName(),
                                'orderAmount' => $order?->getTotal(),
                                'orderNumber' => $order?->getId(),
                                'customerServicePhone' => '212 6 61 61 61 61',
                                'customerServiceEmail' => 'support@ocarz.ma',
                            ]);

                        $this->mailer->send($email);

                        if (null === $order->getCampaign() && $order->getCredit() > 0) {
                            $this->creditRepository->addCredit($user, $order->getCredit());
                        }
                        $this->managerRegistry->getManager()->flush();
                    } else {
                        $response = 'APPROVED';
                    }
                    $campaign = $order->getCampaign();
                    if (null !== $campaign) {
                        $campaign->setStatus(CampaignStatus::Published);
                        $this->managerRegistry->getManager()->flush();
                    }
                } else {
                    $response = 'FAILURE';
                }
            } else {
                $response = 'FAILURE';
            }
            $this->logger->info(sprintf('cmi callback: order: %d, response: %s', $order->getId(), $response));
        } catch (\Throwable $throwable) {
            $this->logger->info(
                sprintf('cmi callback: order: %d, exception %s: ', $order->getId(), $throwable->getMessage())
            );
        }

        return new Response($response);
    }
}
