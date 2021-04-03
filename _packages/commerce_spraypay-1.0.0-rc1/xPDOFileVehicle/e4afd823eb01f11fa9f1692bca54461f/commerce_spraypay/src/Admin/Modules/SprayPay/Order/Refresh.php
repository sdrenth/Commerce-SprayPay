<?php

namespace Sdrenth\Commerce\SprayPay\Admin\Modules\SprayPay\Order;

use modmore\Commerce\Admin\Page;
use Sdrenth\SprayPay\Resources\Payment;
use Sdrenth\SprayPay\SprayPayApiClient;

class Refresh extends Page
{
    public $key = 'spraypay/order/refresh';
    public $title = 'commerce_spraypay.transaction.refresh_status';

    /**
     * @var $SprayPayApiClient;
     */
    protected $client;

    /**
     * Manually retrieve SprayPay transaction status.
     * @return Refresh
     */
    public function setUp()
    {
        $transactionId  = (int) $this->getOption('id', 0);
        $comTransaction = $this->adapter->getObject('comTransaction', $transactionId);

        if (!$comTransaction) {
            return $this->returnError($this->adapter->lexicon('commerce.transaction_not_found', ['id' => $transactionId]));
        }

        $comOrder = $this->adapter->getObject('comOrder', $comTransaction->get('order'));
        if (!$comOrder) {
            return $this->returnError($this->adapter->lexicon('commerce.order_not_found', ['id' => $comTransaction->get('order')]));
        }

        $method = $comTransaction->getOne('Method');
        if (!$method) {
            return $this->returnError('Could not retrieve payment method.');
        }

        try {
            $this->client = new SprayPayApiClient();

            $this->client->setApiKey($method->getProperty('api_key'));
            $this->client->setWebshopId($method->getProperty('webshop_id'));

            if ($this->adapter->getOption('commerce.mode') === 'test') {
                $this->client->setTestMode();
            }

            $payment = new Payment($this->client->orderstatus->get($comTransaction->get('order')));
            switch ($payment->status) {
                case 'PENDING':
                    if (!$comTransaction->isProcessing()) {
                        $comTransaction->markProcessing();
                    }
                    break;
                case 'CANCELLED':
                    if (!$comTransaction->isCancelled()) {
                        $comTransaction->markCancelled('The loan request has not been approved or cancelled afterwards.');
                    }
                    break;
                case 'APPROVED':
                    if (!$comTransaction->isCompleted) {
                        $comTransaction->set('amount', ($payment->amount * 100));
                        $comTransaction->markCompleted();

                        $comOrder->calculate();
                        $comOrder->triggerPaidStatusChange();
                    }
                    break;
                default:
                    return $this->returnError('Unknown transaction status: ' . $payment->status);
                    break;
            }
        } catch (\Exception $exception) {
            $this->commerce->adapter->log(1, 'Failed initialising SprayPayApiClient: ' . $exception->getMessage());

            return $this->returnError($exception->getMessage());
        }

        return $this->returnSuccess('The transaction has been updated! It\'s current status is: ' . $this->adapter->lexicon('commerce.transaction_status.' . $comTransaction->get('status')) . '.');
    }
}