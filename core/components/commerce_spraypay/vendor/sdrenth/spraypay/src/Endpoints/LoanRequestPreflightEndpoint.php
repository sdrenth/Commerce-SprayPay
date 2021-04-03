<?php

namespace Sdrenth\SprayPay\Endpoints;

class LoanRequestPreflightEndpoint extends AbstractEndpoint
{
    const ENDPOINT = 'loanRequest/preflight';

    /**
     * Perform preflight test.
     * @return mixed
     */
    public function preflight(array $params)
    {
        return $this->client->callApi(self::ENDPOINT, $params);
    }
}
