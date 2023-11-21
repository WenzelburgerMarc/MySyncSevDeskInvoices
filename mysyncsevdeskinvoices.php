<?php
/**
 * 2007-2023 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2023 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__).'/DatabaseOperations.php');
require_once(dirname(__FILE__).'/ApiService.php');

class MySyncSevDeskInvoices extends Module
{
    // Module Properties
    private $sevDeskUrl = 'https://my.sevdesk.de/api/v1/';
    private $sevDeskToken;
    private $settings;

    // Database Operations
    private $databaseOperations;
    private $apiService;

    // Constructor
    public function __construct()
    {
        $this->name = 'mysyncsevdeskinvoices';
        $this->tab = 'billing_invoicing';
        $this->version = '1.0.0';
        $this->author = 'Marc From Pangoon';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Sync SevDesk Invoices');
        $this->description = $this->l('This Module Synchronizes PrestaShop Invoices With SevDesk');

        $this->confirmUninstall = $this->l('Are You Sure That You Want To Uninstall This Module? No More Invoices Will Get Synchronized With SevDesk!');

        $this->ps_versions_compliancy = array('min' => '8.0', 'max' => _PS_VERSION_);

        // Database Operations
        if(!isset($this->databaseOperations))
            $this->databaseOperations = new DatabaseOperations();

        // API Service
        if(!isset($this->apiService))
            $this->apiService = new ApiService();
    }

    // Install module
    public function install()
    {
        // Database Operations
        if(!isset($this->databaseOperations))
            $this->databaseOperations = new DatabaseOperations();

        // API Service
        if(!isset($this->apiService))
            $this->apiService = new ApiService();

        $this->settings = [
            'MY_SYNC_SEVDESK_INVOICES_API_TOKEN' => 'bd39be47fc6506bd409ffc500fea3a9a', // TODO: Clear token after finishing development
            'MY_SYNC_SEVDESK_API_REQUEST_URL' => 'https://my.sevdesk.de/api/v1/',
            'MY_SYNC_SEVDESK_DAYS_UNTIL_DELIVERY' => '0',
            'MY_SYNC_SEVDESK_MALE_TITLE' =>  'Herr',
            'MY_SYNC_SEVDESK_FEMALE_TITLE' => 'Frau',
            'MY_SYNC_SEVDESK_NEUTRAL_TITLE' => '',
            'MY_SYNC_SEVDESK_DISCOUNT_TEXT' => 'Rabatt',
            'MY_SYNC_SEVDESK_USE_DELIVERY_ADDRESS' => '0',
            'MY_SYNC_SEVDESK_TIME_TO_PAY' => '0'
        ];

        foreach ($this->settings as $key => $value) {
            Configuration::updateValue($key, $value);
        }


        return $this->installTab() && $this->databaseOperations->installLogSQL() && $this->databaseOperations->installExistingSevDeskInvoicesSQL() && parent::install() && $this->registerHook('actionValidateOrder') && $this->registerHook('actionOrderStatusPostUpdate') && $this->registerHook('actionPaymentConfirmation') && $this->databaseOperations->addLog('Module installed');
    }

    // Uninstall module
    public function uninstall()
    {
        // Database Operations
        if(!isset($this->databaseOperations))
            $this->databaseOperations = new DatabaseOperations();

        // API Service
        if(!isset($this->apiService))
            $this->apiService = new ApiService();

        if(isset($this->settings)){
            foreach ($this->settings as $key => $value) {
                Configuration::deleteByName($key);
            }
        }


        return $this->uninstallTab() && $this->databaseOperations->uninstallLogSQL() && $this->databaseOperations->uninstallExistingSevDeskInvoicesSQL() && parent::uninstall() && $this->unregisterHook('actionValidateOrder') && $this->unregisterHook('actionOrderStatusPostUpdate') && $this->unregisterHook('actionPaymentConfirmation');
    }

    // Add a new admin menu
    public function installTab()
    {
        $languages = Language::getLanguages();
        $tab = new Tab(); // responsible to add a new admin menu
        $tab->class_name = 'AdminSyncSevDeskModule';
        $tab->module = $this->name;
        $tab->id_parent = (int)Tab::getIdFromClassName('DEFAULT');
        $tab->icon = 'panorama_fish_eye';
        $tab->route_name = 'syncsevdesk';
        $tab->active = 1;
        foreach ($languages as $lang) {
            $tab->name[$lang['id_lang']] = $this->l('Sync SevDesk Invoices');
        }

        try {
            $tab->save();
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
        return true;
    }

    // Delete the admin menu
    public function uninstallTab()
    {
        $idTab = (int)Tab::getIdFromClassName('AdminSyncSevDeskModule');
        if ($idTab) {
            $tab = new Tab($idTab);
            try {
                $tab->delete();
            } catch (Exception $e) {
                echo $e->getMessage();
                return false;
            }
        }
        return true;
    }

    // Load the configuration form
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitMyfirstautogeneratedmoduleModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output . $this->renderForm();
    }

    // Create the form that will be displayed in the configuration of your module.
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMyfirstautogeneratedmoduleModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    // Create the structure of your form.
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('API Token'),
                        'name' => 'MY_SYNC_SEVDESK_INVOICES_API_TOKEN',
                        'desc' => $this->l('Enter your SevDesk API Token here.'),
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('API URL'),
                        'name' => 'MY_SYNC_SEVDESK_API_REQUEST_URL',
                        'desc' => $this->l('Enter your SevDesk API URL here. For Example: https://my.sevdesk.de/api/v1/'),
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Days Until Delivery'),
                        'name' => 'MY_SYNC_SEVDESK_DAYS_UNTIL_DELIVERY',
                        'desc' => $this->l('Order Date + Days Until Delivery = Delivery Date in Invoice'),
                        'required' => true,
                        'suffix' => 'days'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Days To Pay'),
                        'name' => 'MY_SYNC_SEVDESK_TIME_TO_PAY',
                        'desc' => $this->l('Order Date + Days To Pay = Latest Pay Date in Invoice'),
                        'required' => true,
                        'suffix' => 'days',
                        'default' => '0'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Male Title'),
                        'name' => 'MY_SYNC_SEVDESK_MALE_TITLE',
                        'desc' => $this->l('Title for male (e.g., Mr.)'),
                        'required' => false,
                        'default' => 'Herr'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Female Title'),
                        'name' => 'MY_SYNC_SEVDESK_FEMALE_TITLE',
                        'desc' => $this->l('Title for female (e.g., Mrs. or Ms.)'),
                        'required' => false,
                        'default' => 'Frau'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Neutral Title'),
                        'name' => 'MY_SYNC_SEVDESK_NEUTRAL_TITLE',
                        'desc' => $this->l('Title for neutral (leave blank if not applicable)'),
                        'required' => false,
                        'default' => ''
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Discount Text'),
                        'name' => 'MY_SYNC_SEVDESK_DISCOUNT_TEXT',
                        'desc' => $this->l('Text for discount (e.g., Discount)'),
                        'required' => false,
                        'default' => 'Rabatt'
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Use Delivery Address'),
                        'name' => 'MY_SYNC_SEVDESK_USE_DELIVERY_ADDRESS',
                        'is_bool' => true,
                        'required' => false,
                        'default' => 0,
                        'desc' => $this->l('If enabled, use the delivery address instead of the billing address in invoices.'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),

                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    // Set values for the inputs.
    protected function getConfigFormValues()
    {
        return array(
            'MY_SYNC_SEVDESK_INVOICES_API_TOKEN' => Configuration::get('MY_SYNC_SEVDESK_INVOICES_API_TOKEN', ''),
            'MY_SYNC_SEVDESK_DAYS_UNTIL_DELIVERY' => Configuration::get('MY_SYNC_SEVDESK_DAYS_UNTIL_DELIVERY', ''),
            'MY_SYNC_SEVDESK_API_REQUEST_URL' => Configuration::get('MY_SYNC_SEVDESK_API_REQUEST_URL', ''),
            'MY_SYNC_SEVDESK_MALE_TITLE' => Configuration::get('MY_SYNC_SEVDESK_MALE_TITLE', ''),
            'MY_SYNC_SEVDESK_FEMALE_TITLE' => Configuration::get('MY_SYNC_SEVDESK_FEMALE_TITLE', ''),
            'MY_SYNC_SEVDESK_NEUTRAL_TITLE' => Configuration::get('MY_SYNC_SEVDESK_NEUTRAL_TITLE', ''),
            'MY_SYNC_SEVDESK_DISCOUNT_TEXT' => Configuration::get('MY_SYNC_SEVDESK_DISCOUNT_TEXT', ''),
            'MY_SYNC_SEVDESK_USE_DELIVERY_ADDRESS' => Configuration::get('MY_SYNC_SEVDESK_USE_DELIVERY_ADDRESS', ''),
            'MY_SYNC_SEVDESK_TIME_TO_PAY' => Configuration::get('MY_SYNC_SEVDESK_TIME_TO_PAY', '')
        );
    }

    // Save Form Data
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

        $this->context->controller->confirmations[] = $this->l('Settings updated');
        $this->sevDeskToken = Configuration::get('MY_SYNC_SEVDESK_INVOICES_API_TOKEN');
        $this->sevDeskUrl = Configuration::get('MY_SYNC_SEVDESK_API_REQUEST_URL');
    }

    // Hook for Create invoice if order gets validated in prestashop
    public function hookActionValidateOrder($params)
    {
        try {
            $this->sevDeskToken = Configuration::get('MY_SYNC_SEVDESK_INVOICES_API_TOKEN');
            $this->sevDeskUrl = Configuration::get('MY_SYNC_SEVDESK_API_REQUEST_URL');

            if (!isset($this->sevDeskToken)) {
                throw new Exception('No sevDesk token found');
            }

            if (!isset($this->sevDeskUrl)) {
                throw new Exception('No sevDesk url found');
            }

            // Check if invoice already exists
            $sevDeskInvoiceId = $this->databaseOperations->getSevDeskInvoiceIdByPsOrderId($params['order']->id);
            if ($sevDeskInvoiceId !== null) {
                throw new Exception('Invoice already exists in sevDesk with id: ' . $sevDeskInvoiceId);
            }

            // Create New Customer
            $sevDeskContactId = $this->apiService->createNewCustomerInSevDesk($this->databaseOperations, $this->sevDeskUrl, $this->sevDeskToken, $params);
            if (!isset($sevDeskContactId)) {
                throw new Exception('Failed to create new customer in sevDesk');
            }
            $this->databaseOperations->addLog('Created new customer in sevDesk with id: ' . $sevDeskContactId);

            // Create New Invoice For Existing Customer
            $invoiceResponse = $this->apiService->createNewInvoiceInSevDesk($this->databaseOperations, $this->sevDeskUrl, $this->sevDeskToken, $params, $sevDeskContactId);
            $createdInvoiceId = json_decode($invoiceResponse, true)['objects']['invoice']['id'];
            if (!isset($createdInvoiceId)) {
                throw new Exception('Failed to create new invoice in sevDesk');
            }
            $this->databaseOperations->addLog('Created new invoice in sevDesk with id: ' . $createdInvoiceId . ' for ps_order with id: ' . $params['order']->id);
            $this->databaseOperations->addExistingSevDeskInvoiceToDb($params['order']->id, $createdInvoiceId);

        } catch (Exception $e) {
            $this->databaseOperations->addLog('Error in hook actionValidateOrder: ' . $e->getMessage());
        }
    }

    // Hook for Book invoice if order gets paid in prestashop
    public function hookActionPaymentConfirmation($params)
    {
        try {
            $this->sevDeskToken = Configuration::get('MY_SYNC_SEVDESK_INVOICES_API_TOKEN');
            $this->sevDeskUrl = Configuration::get('MY_SYNC_SEVDESK_API_REQUEST_URL');

            if (!isset($this->sevDeskToken)) {
                throw new Exception('No sevDesk token found');
            }

            if (!isset($this->sevDeskUrl)) {
                throw new Exception('No sevDesk url found');
            }

            $order_id = $params['id_order'];
            $amountPayed = $this->databaseOperations->getTotalPaidAmountFromOrderId($order_id);

            if($amountPayed === null){
                throw new Exception('No payed amount found for ps_order with id: ' . $order_id);
            }

            $sevDeskInvoiceId = $this->databaseOperations->getSevDeskInvoiceIdByPsOrderId($order_id);

            if ($sevDeskInvoiceId === null) {
                throw new Exception('No invoice found in sevDesk for ps_order with id: ' . $order_id);
            }

            $existingPaidAmount = $this->apiService->getExistingSevDeskPaidAmount($this->databaseOperations, $sevDeskInvoiceId, $this->sevDeskUrl, $this->sevDeskToken);

            if($existingPaidAmount === null){
                throw new Exception('No existing paid amount found for sevDesk invoice with id: ' . $sevDeskInvoiceId);
            }

            $totalAmountPaid = (double)$amountPayed - (double)$existingPaidAmount;

            $paymentData = [
                "amount" => $totalAmountPaid,
                "date" => date('Y-m-d'),
                "type" => "N",
                "checkAccount" => [
                    "id" => (int)$this->apiService->getCheckAccountId($this->sevDeskUrl, $this->sevDeskToken),
                    "objectName" => "CheckAccount"
                ],
                "checkAccountTransaction" => null,
                "createFeed" => true
            ];

            $this->apiService->bookInvoice($this->databaseOperations, $this->sevDeskUrl, $this->sevDeskToken, $sevDeskInvoiceId, $paymentData);
            $this->databaseOperations->addLog('Booked invoice with id: ' . $sevDeskInvoiceId . ' with amount: ' . (double)$totalAmountPaid);

        } catch (Exception $e) {
            $this->databaseOperations->addLog('Error in hook actionPaymentConfirmation: ' . $e->getMessage());
        }
    }

    // Hook for Cancel invoice if order gets canceled in prestashop
    public function hookActionOrderStatusPostUpdate($params)
    {
        try {
            $this->sevDeskToken = Configuration::get('MY_SYNC_SEVDESK_INVOICES_API_TOKEN');

            if (!isset($this->sevDeskToken)) {
                throw new Exception('No sevDesk token found');
            }

            $this->sevDeskUrl = Configuration::get('MY_SYNC_SEVDESK_API_REQUEST_URL');

            if (!isset($this->sevDeskUrl)) {
                throw new Exception('No sevDesk url found');
            }

            // Check if invoice exists
            $order_id = $params['id_order'];
            $sevDeskInvoiceId = $this->databaseOperations->getSevDeskInvoiceIdByPsOrderId($order_id);
            if (!$sevDeskInvoiceId) {
                throw new Exception('No corresponding sevDesk invoice found for order ID: ' . $order_id);
            }

            $newOrderStatus = $params['newOrderStatus']->id;

            if ($newOrderStatus === 6) { // Canceled
                $cancelResponse = $this->apiService->cancelInvoice($this->databaseOperations, $order_id, $this->sevDeskUrl, $this->sevDeskToken);
                if (!$cancelResponse) {
                    throw new Exception('Failed to cancel invoice in sevDesk for order ID: ' . $order_id);
                }
                $this->databaseOperations->addLog($order_id . ': Order status changed to canceled');
            }
        } catch (Exception $e) {
            $this->databaseOperations->addLog('Error in hook actionOrderStatusPostUpdate: ' . $e->getMessage());
        }
    }

}