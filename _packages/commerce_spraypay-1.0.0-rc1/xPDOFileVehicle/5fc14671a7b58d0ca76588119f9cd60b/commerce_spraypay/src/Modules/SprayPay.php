<?php
namespace Sdrenth\Commerce\SprayPay\Modules;

use comTransaction;
use modmore\Commerce\Events\Admin\GeneratorEvent;
use modmore\Commerce\Admin\Configuration\About\ComposerPackages;
use modmore\Commerce\Admin\Sections\SimpleSection;
use modmore\Commerce\Events\Admin\PageEvent;
use modmore\Commerce\Modules\BaseModule;
use modmore\Commerce\Events\Gateways;
use modmore\Commerce\Events\Admin\TransactionActions;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Loader\FilesystemLoader;
use modmore\Commerce\Admin\Widgets\HtmlWidget;
use Sdrenth\Commerce\SprayPay\Admin\Modules\SprayPay\Order\Chargeback\Grid;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/**
 * @see https://docs.modmore.com/en/Commerce/v1/Developer/Example_Modules/Registering_Payment_Gateway.html
 * @see https://docs.modmore.com/en/Commerce/v1/Developer/Payment_Gateways/index.html
 */
class SprayPay extends BaseModule
{
    /**
     * @return string
     */
    public function getName()
    {
        $this->adapter->loadLexicon('commerce_spraypay:default');

        return $this->adapter->lexicon('commerce_spraypay');
    }

    /**
     * @return string
     */
    public function getAuthor()
    {
        return 'Sander Drenth';
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->adapter->lexicon('commerce_spraypay.description');
    }

    /**
     * @param EventDispatcher $dispatcher
     */
    public function initialize(EventDispatcher $dispatcher)
    {
        /* Load our lexicon. */
        $this->adapter->loadLexicon('commerce_spraypay:default');

        /* Add the xPDO package, so Commerce can detect the derivative classes. */
        $root = dirname(__DIR__, 2);
        $path = $root . '/model/';
        $this->adapter->loadPackage('commerce_spraypay', $path);

        /* Add template path to twig. */
        $root = dirname(dirname(__DIR__));
        $loader = $this->commerce->twig->getLoader();
        $loader->addLoader(new FilesystemLoader($root . '/templates/'));

        $dispatcher->addListener(\Commerce::EVENT_GET_PAYMENT_GATEWAYS, [$this, 'registerGateways']);
        $dispatcher->addListener(\Commerce::EVENT_DASHBOARD_TRANSACTION_ACTIONS, [$this, 'addTransactionActions']);
        $dispatcher->addListener(\Commerce::EVENT_DASHBOARD_INIT_GENERATOR, [$this, 'loadPage']);
        $dispatcher->addListener(\Commerce::EVENT_DASHBOARD_PAGE_BEFORE_GENERATE, [$this, 'modifyTransactionPage']);

        /* Add composer libraries to the about section (v0.12+). */
        $dispatcher->addListener(\Commerce::EVENT_DASHBOARD_LOAD_ABOUT, [$this, 'addLibrariesToAbout']);
    }

    /**
     * @param PageEvent $event
     * @return bool
     */
    public function modifyTransactionPage(PageEvent $event)
    {
        $page = $event->getPage();

        if ($page->key !== 'transaction') {
            return false;
        }

        if (($comTransaction = $this->adapter->getObject('comTransaction', $page->getOption('id'))) && ($method = $comTransaction->getOne('Method'))
                && $method->get('gateway') === 'Sdrenth\Commerce\SprayPay\Gateways\SprayPay'
        ) {
            $section = new SimpleSection($this->commerce);
            $section->priority = 1;

            $section->addWidget(new HtmlWidget($this->commerce, ['html' => '<h3>' . $this->adapter->lexicon('commerce_spraypay.chargebacks') . '</h3>']));
            $section->addWidget(new Grid($this->commerce, ['id' => $page->getOption('id')]));

            $page->addSection($section);
        }
    }

    /**
     * @param GeneratorEvent $event
     */
    public function loadPage(GeneratorEvent $event)
    {
        $generator = $event->getGenerator();
        $generator->addPage('spraypay/order/refresh', '\Sdrenth\Commerce\SprayPay\Admin\Modules\SprayPay\Order\Refresh');
        $generator->addPage('spraypay/order/chargeback/create', '\Sdrenth\Commerce\SprayPay\Admin\Modules\SprayPay\Order\Chargeback\Create');
        $generator->addPage('spraypay/order/chargeback/overview', '\Sdrenth\Commerce\SprayPay\Admin\Modules\SprayPay\Order\Chargeback\Overview');
        $generator->addPage('spraypay/order/chargeback/details', '\Sdrenth\Commerce\SprayPay\Admin\Modules\SprayPay\Order\Chargeback\Details');
    }

    /**
     * @param TransactionActions $event
     */
    public function addTransactionActions(TransactionActions $event)
    {
        $transaction = $event->getTransaction();
        $method      = $transaction->getMethod();

        if ($method->get('gateway') === 'Sdrenth\Commerce\SprayPay\Gateways\SprayPay') {
            $actions = $event->getActions();

            $actions[] = [
                'url'   => $this->adapter->makeAdminUrl('spraypay/order/refresh', ['id' => $transaction->get('id')]),
                'title' => $this->adapter->lexicon('commerce_spraypay.transaction.refresh_status'),
                'modal' => true
            ];

            /* If status completed, add option to request chargeback. */
            if ($transaction->get('status') === comTransaction::STATUS_COMPLETED) {
                $actions[] = [
                    'url'   => $this->adapter->makeAdminUrl('spraypay/order/chargeback/create', ['id' => $transaction->get('id')]),
                    'title' => $this->adapter->lexicon('commerce_spraypay.chargeback.create'),
                    'modal' => true
                ];
            }

            $event->setActions($actions);
        }
    }

    /**
     * @param \comModule $module
     * @return array|\modmore\Commerce\Admin\Widgets\Form\Field[]
     */
    public function getModuleConfiguration(\comModule $module)
    {
        return [];
    }

    /**
     * @param Gateways $event
     */
    public function registerGateways(Gateways $event)
    {
        /* Add the GatewayName gateway, and log an error if the class couldn't be found. */
        if (!$event->addGateway(\Sdrenth\Commerce\SprayPay\Gateways\SprayPay::class, 'SprayPay')) {
            $this->adapter->log(1, 'Could not add SprayPay - the class was probably not found');
        }
    }

    /**
     * @param PageEvent $event
     */
    public function addLibrariesToAbout(PageEvent $event)
    {
        $lockFile = dirname(__DIR__, 2) . '/composer.lock';
        if (file_exists($lockFile)) {
            $section = new SimpleSection($this->commerce);
            $section->addWidget(new ComposerPackages($this->commerce, [
                'lockFile'      => $lockFile,
                'heading'       => $this->adapter->lexicon('commerce.about.open_source_libraries') . ' - ' . $this->adapter->lexicon('commerce_spraypay'),
                'introduction'  => '', // Could add information about how libraries are used, if you'd like
            ]));

            $about = $event->getPage();
            $about->addSection($section);
        }
    }
}
