<?php

namespace Sdrenth\SprayPay;

use GuzzleHttp\Client;
use Sdrenth\SprayPay\Endpoints\ChargebackRequestEndpoint;
use Sdrenth\SprayPay\Endpoints\LoanRequestEndpoint;
use Sdrenth\SprayPay\Endpoints\LoanRequestPreflightEndpoint;
use Sdrenth\SprayPay\Endpoints\OrderStatusEndpoint;

class SprayPayApiClient
{
    /**
     * @var ClientInterface
     */
    protected $httpClient;

    protected $testMode = false;

    protected $apiKey;

    protected $webshopId;

    /**
     * @var LoanRequestPreflightEndpoint
     */
    public $loanrequestpreflight;

    /**
     * @var LoanRequestEndpoint
     */
    public $loanrequest;

    /**
     * @var OrderStatusEndpoint
     */
    public $orderstatus;

    /**
     * @var ChargebackRequestEndpoint
     */
    public $chargebackrequest;

    /**
     * SprayPayApiClient constructor.
     */
    public function __construct()
    {
        $this->httpClient = new Client();

        $this->initializeEndpoints();
    }

    /**
     * Get api key.
     * @return mixed
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * Set api key.
     * @param string $key
     */
    public function setApiKey(string $key)
    {
        $this->apiKey = $key;
    }

    /**
     * @return mixed
     */
    public function getWebshopId()
    {
        return $this->webshopId;
    }

    /**
     * @param string $webshopId
     */
    public function setWebshopId(string $webshopId)
    {
        $this->webshopId = $webshopId;
    }

    /**
     * Set the test mode.
     */
    public function setTestMode()
    {
        $this->testMode = true;
    }

    /**
     * Initialize endpoints.
     */
    protected function initializeEndpoints()
    {
        $this->loanrequestpreflight = new LoanRequestPreflightEndpoint($this);
        $this->loanrequest          = new LoanRequestEndpoint($this);
        $this->orderstatus          = new OrderStatusEndpoint($this);
        $this->chargebackrequest    = new ChargebackRequestEndpoint($this);
    }

    /**
     * @param string $endpoint
     * @param array $params
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function callApi(string $endpoint, array $params = [])
    {
        $apiDomain = $this->testMode === true ? 'https://preprod.spraypay-test.nl' : 'https://app.spraypay.nl';
        $url       = $apiDomain . '/api/' . $endpoint;

        $params['webshopId']   = $this->getWebshopId();
        $params['merchantSig'] = $this->calculateSignature($params, $endpoint);

        $response = $this->httpClient->request('POST', $url, [
            'form_params'       => $params,
            'Content-Type'      => 'application/json',
            'allow_redirects'   => [
                'max'             => 1,
                'strict'          => true,      // use "strict" RFC compliant redirects.
                'referer'         => true,      // add a Referer header
                'protocols'       => ['https'], // only allow https URLs
                'track_redirects' => true
            ]
        ]);

        $output = json_decode($response->getBody()->getContents(), true);
        if ($response->hasHeader('X-Guzzle-Redirect-History')) {
            $output['redirect'] = $response->getHeader('X-Guzzle-Redirect-History')[0];
        }

        return $output;
    }

    /**
     * Get signature calculation keys based on endpoint, because some endpoint require different keys for calculation.
     * @param string $endpoint
     * @return array|string[]
     */
    protected function getSignatureCalcKeys(string $endpoint) {
        $keys = ['webshopOrderAmount', 'webshopOrderId', 'orderId', 'webshopCustomerId', 'webshopId', 'returnUrl'];

        if ($endpoint === 'chargebackRequest') {
            $keys = array_merge($keys, ['date', 'amount', 'reason', 'chargebackNotificationUrl']);
        }

        if ($endpoint === 'chargebackRequest/status') {
            $keys = array_merge($keys, ['reference']);
        }

        return $keys;
    }

    /**
     * Calculate signature.
     * @see https://spraypayintegratie.docs.apiary.io/#introduction/hmac-calculation
     * @param array $params
     * @return string
     */
    protected function calculateSignature(array $params = [], string $endpoint)
    {
        $params = array_intersect_key($params, array_flip($this->getSignatureCalcKeys($endpoint)));

        ksort($params, SORT_STRING);
        foreach ($params as $key => $value) {
            if (is_null($value)) {
                $params[$key] = '';
            }

            $params[$key] = str_replace(['\\', ':'], ['\\\\', '\\:'], $params[$key]);
        }

        $signingArray = [];
        foreach ($params as $key => $value) {
            $signingArray[] = $key . ':' . $value;
        }

        $binaryHmac = hash_hmac('sha256', implode(':', $signingArray), $this->getApiKey(), true);

        return base64_encode($binaryHmac);
    }

    /**
     * Validate result notification URLs.
     * @param array $params
     * @return bool
     */
    public function isValidResultNotification(array $params)
    {
        if (!isset($params['signature'])) {
            return false;
        }

        $signature = $params['signature'];
        $params    = array_intersect_key($params, array_flip(['amount', 'reference', 'loanRequestReference', 'result', 'loanRequestResult', 'orderId', 'paymentMethod']));

        ksort($params);

        $signingArray = [];
        foreach ($params as $key => $value) {
            $signingArray[] = $key . '=' . $value;
        }

        $binaryHmac = hash_hmac('sha256', implode('&', $signingArray), $this->getApiKey(), true);

        return $signature === base64_encode($binaryHmac);
    }
}
