<?php

namespace Sdrenth\Commerce\SprayPay\Gateways;

use Commerce;
use comOrder;
use comPaymentMethod;
use comTransaction;
use comTransactionLog;
use modmore\Commerce\Adapter\AdapterInterface;
use modmore\Commerce\Admin\Widgets\Form\TextField;
use modmore\Commerce\Gateways\Exceptions\InvalidStateException;
use modmore\Commerce\Gateways\Exceptions\TransactionException;
use modmore\Commerce\Gateways\Helpers\GatewayHelper;
use modmore\Commerce\Gateways\Interfaces\GatewayInterface;
use modmore\Commerce\Gateways\Interfaces\RedirectTransactionInterface;
use modmore\Commerce\Gateways\Interfaces\TransactionInterface;
use modmore\Commerce\Gateways\Interfaces\ConditionallyAvailableGatewayInterface;
use modmore\Commerce\Gateways\Interfaces\WebhookGatewayInterface;
use Sdrenth\SprayPay\SprayPayApiClient;
use Sdrenth\SprayPay\Resources\Payment;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;

/**
 * Class SprayPay.
 * @package Sdrenth\Commerce\SprayPay\Gateways
 */
class SprayPay implements GatewayInterface, ConditionallyAvailableGatewayInterface, WebhookGatewayInterface
{
    /**
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * @var SprayPayApiClient
     */
    private $client;

    /**
     * @var Commerce
     */
    private $commerce;

    /**
     * @var comPaymentMethod
     */
    private $method;

    /**
     * SprayPay constructor.
     * @param Commerce $commerce
     * @param comPaymentMethod $method
     */
    public function __construct(Commerce $commerce, comPaymentMethod $method)
    {
        $this->commerce = $commerce;
        $this->adapter  = $commerce->adapter;
        $this->method   = $method;

        try {
            $this->client = new SprayPayApiClient();

            $this->client->setApiKey($this->method->getProperty('api_key', ''));
            $this->client->setWebshopId($this->method->getProperty('webshop_id', ''));

            if ($this->adapter->getOption('commerce.mode') === 'test') {
                $this->client->setTestMode();
            }
        } catch (\Exception $exception) {
            $this->commerce->adapter->log(1, 'Failed initialising SprayPayApiClient: ' . $exception->getMessage());
        }
    }

    /**
     * Preflight check to determine if the payment method should be shown.
     * @param comOrder $order
     * @return bool
     */
    public function isAvailableFor(\comOrder $order): bool
    {
        try {
            $response = $this->client->loanrequestpreflight->preflight([
                'emailAddress'       => $order->getBillingAddress()->get('email'),
                'webshopOrderAmount' => number_format(($order->get('total') / 100), 2, '.', ''),
                'webshopOrderId'     => $order->get('id'),
                'webshopCustomerId'  => $order->getBillingAddress()->get('id'),
                'returnUrl'          => ''
            ]);

            if (isset($response) && $response['result'] === 'APPROVED') {
                return true;
            }
        } catch (\Exception $exception) {
            $this->adapter->log(\xPDO::LOG_LEVEL_ERROR, $exception->getMessage());

            return false;
        }

        return false;
    }

    /**
     * @param comOrder $order
     * @return string
     */
    public function view(comOrder $order)
    {
        return '';
    }

    /**
     * Handle the payment submit, returning an up-to-date instance of the PaymentInterface.
     *
     * @param comTransaction $transaction
     * @param array $data
     * @return TransactionInterface|RedirectTransactionInterface
     * @throws TransactionException
     */
    public function submit(comTransaction $transaction, array $data)
    {
        $order = $transaction->getOrder();
        if (!$order) {
            throw new InvalidStateException('Missing order for transaction.');
        }

        $paymentData = [
            'webshopOrderAmount' => number_format($transaction->get('amount') / 100, $transaction->getCurrency()->get('subunits'), '.', ''),
            'webshopOrderId'     => $order->get('id'),
            'webshopCustomerId'  => $order->getBillingAddress()->get('id'),
            'returnUrl'          => GatewayHelper::getReturnUrl($transaction)
        ];

        $address = $order->getBillingAddress() ?: $order->getShippingAddress();
        if ($address) {
            $paymentData = array_merge($paymentData, [
                'emailAddress'  => $address->get('email'),
                'firstName'     => $address->get('firstname'),
                'lastname'      => $address->get('lastname'),
                'street'        => $address->get('address1'),
                'houseNumber'   => $address->get('address2'),
                'postalCode'    => $address->get('zip'),
                'city'          => $address->get('city'),
                'country'       => $address->get('country')
            ]);

            if (!empty($address->get('phone'))) {
                $phoneUtil      = PhoneNumberUtil::getInstance();
                $numberProto    = $phoneUtil->parse($address->get('phone'), $address->get('country'));
                $formatted      = preg_replace('/\s+/', '', $phoneUtil->format($numberProto, PhoneNumberFormat::INTERNATIONAL));
                $formatted      = str_replace('+', '00', $formatted);

                if ($phoneUtil->isValidNumber($numberProto)) {
                    $key                = $phoneUtil->getNumberType($numberProto) === PhoneNumberType::MOBILE ? 'phoneNumberMobile' : 'phoneNumberOther';
                    $paymentData[$key]  = $formatted;
                }
            }
        }

        if ((!isset($paymentData['emailAddress']) || empty($paymentData['emailAddress'])) && $shippingAddress = $order->getShippingAddress()) {
            $paymentData['emailAddress'] = $shippingAddress->get('email');
        }

        if ($shippingAddress = $order->getShippingAddress()) {
            $paymentData['shippingAddress'] = [
                'streetAndNumber'   => $shippingAddress->get('address1'),
                'postalCode'        => $shippingAddress->get('zip'),
                'city'              => $shippingAddress->get('city'),
                'region'            => $shippingAddress->get('state'),
                'country'           => $shippingAddress->get('country'),
            ];
        }

        try {
            $payment = new Payment($this->client->loanrequest->create($paymentData, false, true));
        } catch (ApiException $e) {
            $transaction->log($e->getMessage() . ' for paymentData: ' . json_encode($paymentData), comTransactionLog::SOURCE_GATEWAY);
            throw new TransactionException($e->getMessage());
        }

        return new SprayPayTransaction($order, $payment);
    }

    /**
     * Handle the customer returning to the shop, typically only called after returning from a redirect.
     *
     * @param comTransaction $transaction
     * @param array $data
     * @return MollieTransaction
     * @throws TransactionException
     */
    public function returned(comTransaction $transaction, array $data)
    {
        $order = $transaction->getOrder();
        if (!$order) {
            throw new InvalidStateException('Missing order for transaction.');
        }

        $payment = null;
        try {
            /**
             * This either used for the SprayPay response or payment pending page.
             * When it is the return url from SprayPay we first validate the signature.
             */
            if ((isset($data['loanRequestReference'], $data['amount']) && $this->client->isValidResultNotification($data)) || !isset($data['loanRequestReference'])) {
                /* Update the amount to ensure the transaction amount in Commerce is up-to-date. */
                if (isset($data['loanRequestReference'], $data['amount'])) {
                    $transaction->set('amount', $data['amount'] * 100);
                    $transaction->save();
                }

                $payment = new Payment($this->client->orderstatus->get($order->get('id')));
            }
        } catch (\Exception $e) {
            throw new TransactionException($e->getMessage(), $e->getCode(), $e);
        }

        return new SprayPayTransaction($order, $payment, $data);
    }

    /**
     * Handle an incoming webhook. Webhook URLs, and fetching the transaction in the webhook, happen transparently.
     *
     * $data contains unfiltered information from $_REQUEST.
     *
     * @param comTransaction $transaction
     * @param array $data
     * @return WebhookTransactionInterface
     * @throws TransactionException
     */
    public function webhook(comTransaction $transaction, array $data)
    {
        /* First validate result notification. */
        if ($this->client->isValidResultNotification($data)) {
            /**
             * Handle chargeback status notification.
             */
            if (isset($data['subject'], $data['reference'], $data['status'], $data['message']) && $data['subject'] === 'chargeback') {
                $chargeback = $this->adapter->getObject('comSprayPayChargeback', ['reference' => $data['reference'], 'transaction' => $transaction]);
                if (!$chargeback) {
                    throw new TransactionException(sprintf('Could not find chargeback with reference "%s" for this transaction.', $data['reference']));
                }

                $chargeback->set('status', $data['status']);
                $chargeback->set('message', $data['message']);

                if (!$chargeback->save()) {
                    throw new TransactionException('Failed to save the chargeback.');
                }
            }
        }

        $order = $transaction->getOrder();
        try {
            $payment = new Payment($this->client->orderstatus->get($order->get('id')));
        } catch (\Exception $e) {
            throw new TransactionException($e->getMessage(), $e->getCode(), $e);
        }

        /* Return the Transaction because the handler needs this. */
        return new SprayPayTransaction($order, $payment, $data);
    }

    /**
     * Get gateway properties.
     * @param comPaymentMethod $method
     * @return array|\modmore\Commerce\Admin\Widgets\Form\Field[]
     */
    public function getGatewayProperties(\comPaymentMethod $method)
    {
        $fields = [];

        $fields[] = new TextField($this->commerce, [
            'name'          => 'properties[api_key]',
            'label'         => $this->adapter->lexicon('commerce_spraypay.setting.api_key'),
            'description'   => $this->adapter->lexicon('commerce_spraypay.setting.api_key_desc'),
            'value'         => $method->getProperty('api_key'),
        ]);

        $fields[] = new TextField($this->commerce, [
            'name'          => 'properties[webshop_id]',
            'label'         => $this->adapter->lexicon('commerce_spraypay.setting.webshop_id'),
            'description'   => $this->adapter->lexicon('commerce_spraypay.setting.webshop_id_desc'),
            'value'         => $method->getProperty('webshop_id'),
        ]);

        return $fields;
    }
}
