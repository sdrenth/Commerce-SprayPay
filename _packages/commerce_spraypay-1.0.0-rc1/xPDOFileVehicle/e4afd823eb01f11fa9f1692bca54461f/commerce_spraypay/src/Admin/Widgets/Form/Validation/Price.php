<?php

namespace Sdrenth\Commerce\SprayPay\Admin\Widgets\Form\Validation;

use modmore\Commerce\Admin\Widgets\Form\Validation\Rule;


class Price extends Rule
{
    /**
     * @var string $max Max price in cents.
     */
    protected $max = '';

    /**
     * Date constructor.
     * @param string $max
     */
    public function __construct($max = '')
    {
        $this->max = $max;
    }

    /**
     * @param $value
     * @return boolean
     */
    public function isValid($value)
    {
        if ($value > $this->max) {
            return 'commerce_spraypay.validation.price_max_exceeded';
        }

        return true;
    }
}