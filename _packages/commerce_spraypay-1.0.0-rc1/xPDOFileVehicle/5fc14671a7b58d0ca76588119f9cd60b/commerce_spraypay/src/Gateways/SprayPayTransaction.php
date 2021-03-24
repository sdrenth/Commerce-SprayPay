<?php

namespace Sdrenth\Commerce\SprayPay\Gateways;

use modmore\Commerce\Gateways\Interfaces\TransactionInterface;
use modmore\Commerce\Gateways\Interfaces\RedirectTransactionInterface;

class SprayPayTransaction implements TransactionInterface, RedirectTransactionInterface
{
    /**
     * @var comOrder $order
     */
    private $order;

    /**
     * @var $payment Holds payment.
     */
    private $payment;

    private $data;

    /**
     * SprayPayTransaction constructor.
     * @param $reference@
     */
    public function __construct($comOrder, $payment, $data = [])
    {
        $this->order   = $comOrder;
        $this->payment = $payment;
        $this->data    = $data;
    }

    /**
     * Indicate if the transaction requires the customer to be redirected off-site.
     *
     * @return bool
     */
    public function isRedirect()
    {
        return !empty($this->getRedirectUrl());
    }

    /**
     * @return string Either GET or POST
     */
    public function getRedirectMethod()
    {
        return 'GET';
    }

    /**
     * Return the fully qualified URL to redirect the customer to.
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->payment->redirect;
    }

    /**
     * Return the redirect data as a key => value array, when the redirectMethod is POST.
     *
     * @return array
     */
    public function getRedirectData()
    {
        return [];
    }

    public function isPaid()
    {
        return $this->payment->isPaid();
    }

    public function isAwaitingConfirmation()
    {
        return $this->payment->isPending();
    }

    public function isFailed()
    {
        return false;
    }

    public function isCancelled()
    {
        return $this->payment->isCancelled();
    }

    public function getErrorMessage()
    {
        return '';
    }

    public function getPaymentReference()
    {
        return $this->data['loanRequestReference'];
    }

    public function getExtraInformation()
    {
        return [];
    }

    public function getData()
    {
        return [];
    }
}