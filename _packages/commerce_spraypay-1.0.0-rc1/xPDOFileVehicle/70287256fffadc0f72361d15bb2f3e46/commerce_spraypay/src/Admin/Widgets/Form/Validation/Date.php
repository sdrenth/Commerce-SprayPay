<?php

namespace Sdrenth\Commerce\SprayPay\Admin\Widgets\Form\Validation;

use modmore\Commerce\Admin\Widgets\Form\Validation\Rule;


class Date extends Rule
{
    /**
     * @var string $max Timestamp of maximum date.
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
        if (empty($value) || $value === '1970-01-01') {
            return 'commerce.validation.required';
        }

        if (!empty($this->max) && strtotime($value) >= $this->max) {
            return 'commerce_spraypay.validation.date_max_exceeded';
        }

        return true;
    }
}