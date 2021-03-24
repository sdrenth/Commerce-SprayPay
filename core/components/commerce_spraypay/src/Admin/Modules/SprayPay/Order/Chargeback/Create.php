<?php

namespace Sdrenth\Commerce\SprayPay\Admin\Modules\SprayPay\Order\Chargeback;

use modmore\Commerce\Admin\Page;
use modmore\Commerce\Admin\Sections\SimpleSection;

/**
 * Class Create
 * @package Sdrenth\Commerce\SprayPay\Admin\Modules\SprayPay\Order\Chargeback
 */
class Create extends Page
{
    protected $classKey = 'comTransaction';
    public $key = 'spraypay-chargeback-form';
    public $title = 'commerce_spraypay.chargeback.create';

    public static $permissions = ['commerce'];

    /**
     * @param array $options
     * @return $this|Create
     */
    public function setUp(array $options = [])
    {
        $objectId = (int) $this->getOption('id', 0);
        $exists   = $this->adapter->getCount($this->classKey, ['id' => $objectId]);

        if ($exists) {
            $section = new SimpleSection($this->commerce, [
                'title'     => $this->title
            ]);

            $section->addWidget(new Form($this->commerce, ['id' => $objectId]));
            $this->addSection($section);

            return $this;
        }
    }
}
