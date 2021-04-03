<?php

namespace Sdrenth\SprayPay\Endpoints;

class ChargebackRequestEndpoint extends AbstractEndpoint
{
    const ENDPOINT = 'chargebackRequest';

    /**
     * Create chargeback.
     * @param array $params
     * @return mixed
     */
    public function create(array $params)
    {
        return $this->client->callApi(self::ENDPOINT, $params);
    }

    /**
     * Retrieve the status of a chargeback.
     * @param array $params
     * @return mixed
     */
    public function getStatus(array $params)
    {
        return $this->client->callApi(self::ENDPOINT . '/status', $params);
    }
}