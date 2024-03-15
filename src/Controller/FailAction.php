<?php

namespace Acme\SyliusExamplePlugin\Controller;

use App\Repository\OrderRepository;
use App\Util\CmiPaymentTool;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class FailAction extends AbstractController
{
    public function __construct(
        private readonly CmiPaymentTool $cmiPaymentTool,
        private readonly OrderRepository $orderRepository,
        private readonly string $appBaseUrl,
    ) {
    }

    #[Route(path: '/orders/{id}/payment_fail
    ', name: 'cmi_payment_fail')]
    public function __invoke(int $id, Request $request): RedirectResponse
    {
        $order = $this->orderRepository->find($id);
        if (null === $order) {
            throw new BadRequestException('Not Found');
        }
        $postData = $request->request->all();
        if ($postData) {
            $actualHash = $this->cmiPaymentTool->hashValue($postData);
            $retrievedHash = $postData['HASH'];
            if ($retrievedHash === $actualHash && '00' === $postData['ProcReturnCode']) {
                return $this->redirect($this->appBaseUrl.'/order-purchase?orderId='.$order->getId().'&payment=fail&step=3');
            }
        }

        return $this->redirect($this->appBaseUrl.'/order-purchase?orderId='.$order->getId().'&payment=fail&step=3');
    }
}
