<?php

namespace Sdrenth\Commerce\SprayPay\Admin\Modules\SprayPay\Order\Chargeback;

use modmore\Commerce\Admin\Widgets\Form\DateField;
use modmore\Commerce\Admin\Widgets\Form\NumberField;
use modmore\Commerce\Admin\Widgets\Form\TextField;
use modmore\Commerce\Admin\Widgets\Form\Validation\Required;
use modmore\Commerce\Admin\Widgets\FormWidget;
use modmore\Commerce\Gateways\Helpers\GatewayHelper;
use Sdrenth\Commerce\SprayPay\Admin\Widgets\Form\Validation\Date;
use Sdrenth\Commerce\SprayPay\Admin\Widgets\Form\Validation\Price;
use Sdrenth\SprayPay\SprayPayApiClient;

/**
 * Class Form
 * @package modmore\Commerce\Admin\Configuration\PaymentMethods
 *
 * @property $record \comPaymentMethod
 */
class Form extends FormWidget
{
    protected $classKey = 'comTransaction';
    public $key = 'spraypay-chargeback-form';
    public $title = '';

    /**
     * @var $SprayPayApiClient;
     */
    protected $client;

    protected $hideSaveBtn = false;

    /**
     * @param bool $bool
     */
    public function setHideSaveBtn(bool $bool)
    {
        $this->hideSaveBtn = $bool;
    }

    /**
     * @param array $options
     * @return array|\modmore\Commerce\Admin\Widgets\Form\Field[]
     */
    public function getFields(array $options = array())
    {
        $fields = [];

        $fields[] = new DateField($this->commerce, [
            'name'          => 'date',
            'label'         => $this->adapter->lexicon('commerce_spraypay.request_date'),
            'description'   => $this->adapter->lexicon('commerce_spraypay.request_date_desc'),
            'validation'    => [
                new Date(time())
            ]
        ]);

        $fields[] = new NumberField($this->commerce, [
            'name'          => 'amount',
            'label'         => $this->adapter->lexicon('commerce_spraypay.request_amount'),
            'input_class'   => 'commerce-field-currency',
            'description'   => $this->adapter->lexicon('commerce_spraypay.request_amount_desc'),
            'validation'    => [
                new Required(),
                new Price($this->record->get('amount'))
            ]
        ]);

        $fields[] = new TextField($this->commerce, [
            'name'          => 'reason',
            'label'         => $this->adapter->lexicon('commerce_spraypay.request_reason'),
            'description'   => $this->adapter->lexicon('commerce_spraypay.request_reason_desc')
        ]);

        return $fields;
    }

    /**
     * @param array $values
     * @return mixed|string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function handleSubmit(array $values)
    {
        if (!$this->validate($values)) {
            $values['hasErrors'] = 1;

            return $this->generateForm($values);
        }

        $method = $this->record->getOne('Method');
        if (!$method) {
            $values['hasErrors']     = 1;
            $values['general_error'] = $this->adapter->lexicon('commerce.spraypay.transaction.method_not_retrieved');

            return $this->generateForm($values);
        }

        try {
            $this->client = new SprayPayApiClient();

            $this->client->setApiKey($method->getProperty('api_key'));
            $this->client->setWebshopId($method->getProperty('webshop_id'));

            if ($this->adapter->getOption('commerce.mode') === 'test') {
                $this->client->setTestMode();
            }

            $result = $this->client->chargebackrequest->create([
                'date'                      => $values['date'],
                'amount'                    => number_format(($values['amount'] / 100), 2, '.', ''),
                'chargebackNotificationUrl' => GatewayHelper::getNotifyURL($this->record) . '&subject=chargeback',
                'orderId'                   => $this->record->get('order'),
                'reason'                    => $values['reason']
            ]);

            if ($result['status'] === 'CHECK_FAILED') {
                $values['hasErrors']     = 1;
                $values['general_error'] = $result['message'];

                return $this->generateForm($values);
            }

            $chargeback = $this->adapter->newObject('comSprayPayChargeback');
            $chargeback->fromArray([
                'transaction'       => $this->record->get('id'),
                'request_date'      => strtotime($values['date']),
                'request_amount'    => $values['amount'],
                'request_reason'    => $values['reason'],
                'status'            => $result['status'],
                'reference'         => $result['reference'],
                'message'           => $result['message']
            ]);

            if (!$chargeback->save()) {
                $values['hasErrors']     = 1;
                $values['general_error'] = $this->adapter->lexicon('commerce_spraypay.chargeback.save_failed');

                return $this->generateForm($values);
            }

            $values['save_success'] = $this->adapter->lexicon('commerce_spraypay.chargeback.created', [
                'reference' => $result['reference'],
                'status'    => $result['status']
            ]);

            $this->setHideSaveBtn(true);
        } catch (\Exception $exception) {
            $values['hasErrors']     = 1;
            $values['general_error'] = $exception->getMessage();

            return $this->generateForm($values);
        }

        return $this->generateForm($values);
    }

    /**
     * @param $inputOptions
     * @return mixed|string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function generateForm($inputOptions)
    {
        $phs = array_merge($this->getMeta(), [
            'values'        => $inputOptions,
            'fields'        => $this->_getFields($inputOptions),
            'action'        => $this->getFormAction($inputOptions),
            'submitToModal' => $this->submitToModal,
            'hideSaveBtn'   => $this->hideSaveBtn
        ]);

        return $this->commerce->twig->render('spraypay/chargeback/form.twig', $phs);
    }

    /**
     * @param array $options
     * @return string
     */
    public function getFormAction(array $options = array())
    {
        return $this->adapter->makeAdminUrl('spraypay/order/chargeback/create', ['id' => $this->record->get('id')]);
    }
}
