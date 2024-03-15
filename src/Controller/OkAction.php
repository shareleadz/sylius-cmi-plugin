<?php

namespace Acme\SyliusExamplePlugin\Controller;

use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Util\CmiPaymentTool;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class OkAction extends AbstractController
{
    public function __construct(
        private readonly CmiPaymentTool $cmiPaymentTool,
        private readonly OrderRepository $orderRepository,
        private readonly ProductRepository $productRepository,
        private readonly UserRepository $userRepository,
        private readonly Security $security,
        private readonly string $appBaseUrl,
    ) {
    }

    #[Route(path: '/orders/{id}/payment_ok
    ', name: 'cmi_payment_ok')]
    public function __invoke(int $id, Request $request,EntityManagerInterface $entityManager): RedirectResponse
    {
        $order = $this->orderRepository->find($id);
        if (null === $order) {
            throw new BadRequestException('Not Found');
        }
        $user = $this->userRepository->findOneBy(['email' => $this->security->getUser()?->getUserIdentifier()]);
        if (!$user || $user->getId() !== $order->getUser()?->getId()) {
            throw new BadRequestException('Unauthorized');
        }
        $postData = $request->request->all();
        if ($postData) {
            $actualHash = $this->cmiPaymentTool->hashValue($postData);
            $retrievedHash = $postData['HASH'];
            if ($retrievedHash === $actualHash && '00' === $postData['ProcReturnCode']) {
//                 return $this->redirect($this->appBaseUrl.'/orders/'.$order->getId().'/pay/success');
                if($order->getProduct() !== null){
                    $product = $this->productRepository->find($order->getProduct()->getId());
                    $product->setManagedByOcarz(true);
                    $entityManager->persist($product);
                    $entityManager->flush();
                    return $this->redirect($this->appBaseUrl.'/order-purchase?orderId='.$order->getId().'&payment=success&step=3');
                }
                 return $this->redirect($this->appBaseUrl.'/announces');
            }
        }

        return $this->redirect($this->appBaseUrl.'/order-purchase?orderId='.$order->getId().'&payment=fail&step=3');
    }
}
