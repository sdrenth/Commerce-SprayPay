<?php

namespace Sdrenth\SprayPay\Endpoints;

class LoanRequestEndpoint extends AbstractEndpoint
{
    const ENDPOINT = 'loanRequest';

    /**
     * Create loan request.
     * @param array $params
     * @param boolean $returnNewOrderStatus
     * @param boolean $redirect
     * @return mixed
     */
    public function create(array $params, $returnNewOrderStatus = false, $redirect = false)
    {
        $endpoint = self::ENDPOINT;

        $variables = [];
        if ($returnNewOrderStatus) {
            $variables['newOrderStatus'] = 'true';
        }

        if ($redirect) {
            $variables['redirect'] = 'true';
        }

        if (count($variables) > 0) {
            $endpoint .= '?' . http_build_query($variables);
        }

        return $this->client->callApi($endpoint, $params);
    }
}
