<?php

namespace Sdrenth\Commerce\SprayPay\Admin\Modules\SprayPay\Order\Chargeback;

use modmore\Commerce\Admin\Widgets\GridWidget;
use modmore\Commerce\Admin\Util\Action;
use modmore\Commerce\Admin\Util\Column;

class Grid extends GridWidget
{
    public $key = 'chargebacks-table';
    public $title = '';
    public $defaultSort = 'received_on';
    public $defaultSortDir = 'DESC';

    /**
     * @var int[] $totals Holds all totals.
     */
    protected $totals = [
        'requested' => 0,
        'approved'  => 0
    ];

    /**
     * @param array $options
     * @return array
     */
    public function getItems(array $options = array())
    {
        $items = [];

        $query = $this->adapter->newQuery('comSprayPayChargeback');
        $query->where(['transaction' => $this->getOption('id')]);

        $count = $this->adapter->getCount('comSprayPayChargeback', $query);
        $this->setTotalCount($count);

        foreach ($this->adapter->getIterator('comSprayPayChargeback', $query) as $item) {
            $this->totals['requested'] += $item->get('request_amount');

            if ($item->get('status') === 'APPROVED') {
                $this->totals['approved'] += $item->get('request_amount');
            }

            $items[] = $this->prepareItem($item);
        }

        return $items;
    }

    /**
     * @param array $inputOptions
     * @return array
     */
    public function getPagination(array $inputOptions)
    {
        return [];
    }

    /**
     * @param array $options
     * @return array
     */
    public function getTopToolbar(array $options = array())
    {
        return [];
    }

    /**
     * @return bool
     */
    public function hasActions()
    {
        return true;
    }

    /**
     * @param array $options
     * @return array[]|Column[]
     */
    public function getColumns(array $options = array())
    {
        return [
            new Column('request_date', $this->adapter->lexicon('commerce_spraypay.request_date'), false),
            new Column('request_amount', $this->adapter->lexicon('commerce_spraypay.request_amount'), false),
            new Column('status', $this->adapter->lexicon('commerce_spraypay.status'), false),
            new Column('reference', $this->adapter->lexicon('commerce_spraypay.reference'), false),
        ];
    }

    /**
     * @param \comSprayPayChargeback $chargeback
     * @return array
     */
    public function prepareItem(\comSprayPayChargeback $chargeback)
    {
        $item                   = $chargeback->toArray();
        $item['detail_url']     = $this->adapter->makeAdminUrl('spraypay/order/chargeback/details', ['id' => $chargeback->get('id')]);
        $item['request_date']   = $this->commerce->formatValue($item['request_date'], 'date');
        $item['request_amount'] = $this->commerce->formatValue($item['request_amount'], 'financial');
        $item['actions']        = [];
        $item['actions'][]      = (new Action())
            ->setUrl($item['detail_url'])
            ->setTitle($this->adapter->lexicon('commerce.order.view_details'))
            ->setModal(true);

        return $item;
    }

    /**
     * @return mixed
     */
    public function getNoResults()
    {
        return $this->adapter->lexicon('commerce_spraypay.chargeback.grid.no_results');
    }

    /**
     * @param array $inputOptions
     * @return array|mixed
     */
    public function getFooterRows(array $inputOptions)
    {
        $rows[] = [
            [
                'key'      => 'tr_label',
                'colspan'  => 2,
                'value'    => $this->adapter->lexicon('commerce_spraypay.chargeback.total_requested'),
                'classes'  => 'right aligned'
            ],
            [
                'key'      => 'tr_value',
                'colspan'  => 2,
                'value'    => $this->commerce->formatValue($this->totals['requested'], 'financial')
            ], [
                'key'       => '',
                'colspan'   => 2,
                'value'     => '&nbsp;'
            ]
        ];

        $transaction = $this->adapter->getObject('comTransaction', $this->getOption('id'));
        $rows[] = [
            [
                'key'      => 'trans_label',
                'colspan'  => 2,
                'value'    => $this->adapter->lexicon('commerce_spraypay.chargeback.transaction amount'),
                'classes'  => 'right aligned'
            ],
            [
                'key'      => 'ta_value',
                'colspan'  => 2,
                'value'    => $this->commerce->formatValue($transaction->get('amount'), 'financial')
            ], [
                'key'       => '',
                'colspan'   => 2,
                'value'     => '&nbsp;'
            ]
        ];

        $rows[] = [
            [
                'key'      => 'ta_label',
                'colspan'  => 2,
                'value'    => $this->adapter->lexicon('commerce_spraypay.chargeback.total_approved'),
                'classes'  => 'right aligned'
            ],
            [
                'key'      => 'ta_value',
                'colspan'  => 2,
                'value'    => $this->commerce->formatValue($this->totals['approved'], 'financial')
            ], [
                'key'       => '',
                'colspan'   => 2,
                'value'     => '&nbsp;'
            ]
        ];

        $rows[] = [
            [
                'key'      => 'total_label',
                'colspan'  => 2,
                'value'    => '<b>' . $this->adapter->lexicon('commerce_spraypay.chargeback.total_received') . '</b>',
                'classes'  => 'right aligned'
            ],
            [
                'key'      => 'total_value',
                'colspan'  => 2,
                'value'    => '<b>' . $this->commerce->formatValue($transaction->get('amount') - $this->totals['approved'], 'financial') . '</b>'
            ], [
                'key'       => '',
                'colspan'   => 2,
                'value'     => '&nbsp;'
            ]
        ];

        return $rows;
    }

    /**
     * @param array $phs
     * @return string
     * @throws \modmore\Commerce\Exceptions\ViewException
     */
    public function render(array $phs)
    {
        $phs['actions'] = [];

        $phs['actions'][] = [
            'url'   => $this->adapter->makeAdminUrl('spraypay/order/chargeback/create', ['id' => $this->getOption('id')]),
            'title' => $this->adapter->lexicon('commerce_spraypay.chargeback.create'),
            'modal' => true,
            'icon'  => 'plus',
            'class' => 'primary'
        ];

        return $this->commerce->view()->render('admin/transaction/chargebacks/grid.twig', $phs);
    }
}
