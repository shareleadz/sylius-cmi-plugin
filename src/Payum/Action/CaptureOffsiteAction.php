<?php

namespace Acme\SyliusExamplePlugin\Payum\Action;

use Acme\SyliusExamplePlugin\Payum\SyliusApi;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Reply\HttpPostRedirect;
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\Notify;

class CaptureOffsiteAction implements ActionInterface, ApiAwareInterface
{
    /** @var SyliusApi */
    private $api;

    /**
     * @param mixed $api
     *
     * @throws UnsupportedApiException if the given Api is not supported.
     */
    public function setApi($api)
    {
        if (false === $api instanceof SyliusApi) {
            throw new UnsupportedApiException('Not supported.');
        }

        $this->api = $api;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        $httpRequest = new GetHttpRequest();
        //$this->payment->execute($httpRequest);
        throw new HttpPostRedirect(
            "https://google.com",
        //$this->api->getNewPaymentUrl(),
        //$this->api->buildFormParamsForPostRequest($model->toUnsafeArray())
        );
        if ($httpRequest->request) {
            //$model->replace($httpRequest->request);
            //$this->payment->execute(new Notify($model));
        }
        else {
            throw new HttpPostRedirect(
                "https://google.com",
                //$this->api->getNewPaymentUrl(),
                //$this->api->buildFormParamsForPostRequest($model->toUnsafeArray())
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess
            ;
    }
}
