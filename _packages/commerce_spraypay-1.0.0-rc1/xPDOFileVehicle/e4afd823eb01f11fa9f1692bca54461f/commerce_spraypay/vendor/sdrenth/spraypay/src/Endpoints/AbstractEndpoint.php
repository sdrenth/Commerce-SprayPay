<?php

namespace Sdrenth\SprayPay\Endpoints;

abstract class AbstractEndpoint
{
    const ENDPOINT = '';

    protected $client;

    public function __construct($client)
    {
        $this->client = $client;
    }
}