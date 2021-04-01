<?php

namespace Sdrenth\Commerce\SprayPay\Admin\Modules\SprayPay\Order\Chargeback;

use modmore\Commerce\Admin\Page;
use modmore\Commerce\Admin\Sections\SimpleSection;
use modmore\Commerce\Admin\Widgets\HtmlWidget;
use Sdrenth\SprayPay\SprayPayApiClient;

/**
 * Class Details.
 * @package Sdrenth\Commerce\SprayPay\Admin\Modules\SprayPay\Order\Chargeback
 */
class Details extends Page
{
    protected $classKey = 'comSprayPayChargeback';
    public $key = 'spraypay-chargeback-details';
    public $title = 'commerce_spraypay.transaction.chargeback.view';

    public static $permissions = ['commerce'];

    /**
     * @param $message
     * @return $this|Details
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function returnSuccess($message)
    {
        $section = new SimpleSection($this->commerce, [
            'title' => $this->getTitle()
        ]);

        $html = $this->commerce->twig->render('admin/widgets/messages/custom/success.twig', [
            'message' => $message
        ]);

        $section->addWidget(new HtmlWidget($this->commerce, [
            'html' => $html,
        ]));

        $this->addSection($section);

        return $this;
    }

    /**
     * @param $message
     * @return $this
     */
    public function returnError($message)
    {
        $section = new SimpleSection($this->commerce, [
            'title' => $this->getTitle()
        ]);

        $html = $this->commerce->twig->render('admin/widgets/messages/custom/failure.twig', [
            'message' => $message
        ]);

        $section->addWidget(new HtmlWidget($this->commerce, [
            'html' => $html,
        ]));

        $this->addSection($section);

        return $this;
    }

    /**
     * @param array $options
     * @return $this|Details
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function setUp(array $options = [])
    {
        if ($chargeback = $this->adapter->getObject($this->classKey, $this->getOption('id'))) {
            if ($this->getOption('refresh')) {
                try {
                    $transaction = $this->adapter->getObject('comTransaction', $chargeback->get('transaction'));
                    $method      = $transaction->getOne('Method');

                    $this->client = new SprayPayApiClient();
                    $this->client->setApiKey($method->getProperty('api_key'));
                    $this->client->setWebshopId($method->getProperty('webshop_id'));

                    if ($this->adapter->getOption('commerce.mode') === 'test') {
                        $this->client->setTestMode();
                    }

                    $result = $this->client->chargebackrequest->getStatus(['reference' => $chargeback->get('reference')]);
                    if (!isset($result['status'], $result['reason'])) {
                        $this->returnError($this->adapter->lexicon('commerce_spraypay.chargeback.response_incomplete'));
                    } else {
                        $chargeback->set('status', $result['status']);
                        $chargeback->set('reason', $result['reason']);

                        if ($chargeback->save()) {
                            $this->returnSuccess($this->adapter->lexicon('commerce_spraypay.chargeback.updated'));
                        } else {
                            $this->returnError($this->adapter->lexicon('commerce_spraypay.chargeback.save_failed'));
                        }
                    }
                } catch(\Exception $exception) {
                    $this->returnError($exception->getMessage());
                }
            }

            $section = new SimpleSection($this->commerce, [
                'title'     => $this->title
            ]);

            $phs                   = $chargeback->toArray();
            $phs['request_amount'] = $this->commerce->formatValue($phs['request_amount'], 'financial');
            $phs['request_date']   = $this->commerce->formatValue($phs['request_date'], 'date');
            $phs['actions']        = [];

            $phs['actions'][] = [
                'url'   => $this->adapter->makeAdminUrl('transaction', ['id' => $chargeback->get('transaction')]),
                'title' => $this->adapter->lexicon('commerce.view_transaction'),
                'modal' => true,
                'icon'  => 'eye',
                'class' => 'primary'
            ];

            $phs['actions'][] = [
                'url'   => $this->adapter->makeAdminUrl('spraypay/order/chargeback/details', ['id' => $chargeback->get('id'), 'refresh' => true]),
                'title' => $this->adapter->lexicon('commerce_spraypay.chargeback.refresh'),
                'modal' => true,
                'icon'  => 'refresh',
                'class' => 'primary'
            ];

            $html = $this->commerce->twig->render('admin/transaction/chargebacks/detail.twig', $phs);

            $section->addWidget(new HtmlWidget($this->commerce, ['html' => $html]));
            $this->addSection($section);

            return $this;
        }
    }
}
