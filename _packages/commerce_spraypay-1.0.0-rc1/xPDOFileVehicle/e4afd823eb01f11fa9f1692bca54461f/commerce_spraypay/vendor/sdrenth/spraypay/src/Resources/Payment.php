<?php

namespace Sdrenth\SprayPay\Resources;

class Payment
{
    const STATUS_PENDING = 'PENDING';

    const STATUS_APPROVED = 'APPROVED';

    const STATUS_CANCELLED = 'CANCELLED';

    public $status = self::STATUS_PENDING;

    public $amount = 0;

    public $redirect = '';

    /**
     * Payment constructor.
     * @param $apiResult
     */
    public function __construct($apiResult)
    {
        foreach ($apiResult as $property => $value) {
            $this->{$property} = $value;
        }
    }

    /**
     * Determine if payment is pending.
     * @return bool
     */
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Determine if payment should be marked as if paid.
     * @return bool
     */
    public function isPaid()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Determine if payment is cancelled.
     * @return bool
     */
    public function isCancelled()
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}