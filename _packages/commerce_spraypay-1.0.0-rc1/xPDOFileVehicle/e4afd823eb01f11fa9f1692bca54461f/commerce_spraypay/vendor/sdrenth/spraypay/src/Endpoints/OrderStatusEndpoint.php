<?php

namespace Sdrenth\SprayPay\Endpoints;

class OrderStatusEndpoint extends AbstractEndpoint
{
    const ENDPOINT = 'order/status';

    /**
     * Return the order status.
     * @param int $orderId
     * @return mixed
     */
    public function get(int $orderId)
    {
        return $this->client->callApi(self::ENDPOINT, ['orderId' => $orderId]);
    }
}