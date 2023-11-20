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

class MySyncSevDeskInvoices extends Module
{
    // Module Properties
    private $sevDeskUrl = 'https://my.sevdesk.de/api/v1/';
    private $sevDeskToken;
    private $settings;

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

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    // Install module
    public function install()
    {
        $this->settings = [
            'MY_SYNC_SEVDESK_INVOICES_API_TOKEN' => 'bd39be47fc6506bd409ffc500fea3a9a', // TODO: Clear token after finishing development
            'MY_SYNC_SEVDESK_DAYS_UNTIL_DELIVERY' => '0',
            'My_SYNC_SEVDESK_API_URL' => 'https://my.sevdesk.de/api/v1/',
            'MY_SYNC_SEVDESK_MALE_TITLE' =>  'Herr',
            'MY_SYNC_SEVDESK_FEMALE_TITLE' => 'Frau',
            'MY_SYNC_SEVDESK_NEUTRAL_TITLE' => '',
            'MY_SYNC_SEVDESK_DISCOUNT_TEXT' => 'Rabatt'
        ];

        foreach ($this->settings as $key => $value) {
            Configuration::updateValue($key, $value);
        }

        return $this->installTab() && $this->installLogSQL() && $this->installExistingSevDeskInvoicesSQL() && parent::install() && $this->registerHook('actionValidateOrder') && $this->registerHook('actionOrderStatusPostUpdate') && $this->registerHook('actionPaymentConfirmation') && $this->addLog('Module installed');
    }

    // Uninstall module
    public function uninstall()
    {

        foreach ($this->settings as $key => $value) {
            Configuration::deleteByName($key);
        }

        return $this->uninstallTab() && $this->uninstallLogSQL() && $this->uninstallExistingSevDeskInvoicesSQL() && parent::uninstall() && $this->unregisterHook('actionValidateOrder') && $this->unregisterHook('actionOrderStatusPostUpdate') && $this->unregisterHook('actionPaymentConfirmation');
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

    // Install sevDesk invoices table in DB
    public function installExistingSevDeskInvoicesSQL()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'sevdesk_invoices` (
        `id_sevdesk_invoice` INT(11) NOT NULL AUTO_INCREMENT,
        `id_order` INT(11) NOT NULL,
        `id_sevdesk` INT(11) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_sevdesk_invoice`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return $this->executeSqlQuery($sql);
    }

    // Uninstall sevDesk invoices table from DB
    public function uninstallExistingSevDeskInvoicesSQL()
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'sevdesk_invoices`;';
        return $this->executeSqlQuery($sql);
    }

    // Install log table in DB
    public function installLogSQL()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'sevdesk_logs` (
        `id_log` INT(11) NOT NULL AUTO_INCREMENT,
        `log` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_log`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return $this->executeSqlQuery($sql);
    }

    // Uninstall log table from DB
    public function uninstallLogSQL()
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'sevdesk_logs`;';
        return $this->executeSqlQuery($sql);
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
                        'label' => $this->l('API URL'),
                        'name' => 'MY_SYNC_SEVDESK_API_URL',
                        'desc' => $this->l('Enter your SevDesk API URL here.  For Example: https://my.sevdesk.de/api/v1/'),
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('API Token'),
                        'name' => 'MY_SYNC_SEVDESK_INVOICES_API_TOKEN',
                        'desc' => $this->l('Enter your SevDesk API Token here.'),
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
                    )

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
            'MY_SYNC_SEVDESK_API_URL' => Configuration::get('MY_SYNC_SEVDESK_API_URL', ''),
            'MY_SYNC_SEVDESK_MALE_TITLE' => Configuration::get('MY_SYNC_SEVDESK_MALE_TITLE', ''),
            'MY_SYNC_SEVDESK_FEMALE_TITLE' => Configuration::get('MY_SYNC_SEVDESK_FEMALE_TITLE', ''),
            'MY_SYNC_SEVDESK_NEUTRAL_TITLE' => Configuration::get('MY_SYNC_SEVDESK_NEUTRAL_TITLE', ''),
            'MY_SYNC_SEVDESK_DISCOUNT_TEXT' => Configuration::get('MY_SYNC_SEVDESK_DISCOUNT_TEXT', '')
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
        $this->sevDeskUrl = Configuration::get('MY_SYNC_SEVDESK_API_URL');
    }

    // Hook for Create invoice if order gets validated in prestashop
    public function hookActionValidateOrder($params)
    {
        try {
            $this->sevDeskToken = Configuration::get('MY_SYNC_SEVDESK_INVOICES_API_TOKEN');
            $this->sevDeskUrl = Configuration::get('MY_SYNC_SEVDESK_API_URL');

            if (!isset($this->sevDeskToken)) {
                throw new Exception('No sevDesk token found');
            }

            if (!isset($this->sevDeskUrl)) {
                throw new Exception('No sevDesk url found');
            }

            // Check if invoice already exists
            $sevDeskInvoiceId = $this->getSevDeskInvoiceIdByPsOrderId($params['order']->id);
            if ($sevDeskInvoiceId !== null) {
                throw new Exception('Invoice already exists in sevDesk with id: ' . $sevDeskInvoiceId);
            }

            // Create New Customer
            $sevDeskContactId = $this->createNewCustomerInSevDesk($this->sevDeskUrl, $this->sevDeskToken, $params);
            if (!isset($sevDeskContactId)) {
                throw new Exception('Failed to create new customer in sevDesk');
            }
            $this->addLog('Created new customer in sevDesk with id: ' . $sevDeskContactId);

            // Create New Invoice For Existing Customer
            $invoiceResponse = $this->createNewInvoiceInSevDesk($this->sevDeskUrl, $this->sevDeskToken, $params, $sevDeskContactId);
            $createdInvoiceId = json_decode($invoiceResponse, true)['objects']['invoice']['id'];
            if (!isset($createdInvoiceId)) {
                throw new Exception('Failed to create new invoice in sevDesk');
            }
            $this->addLog('Created new invoice in sevDesk with id: ' . $createdInvoiceId . ' for ps_order with id: ' . $params['order']->id);
            $this->addExistingSevDeskInvoiceToDb($params['order']->id, $createdInvoiceId);

        } catch (Exception $e) {
            $this->addLog('Error in hook actionValidateOrder: ' . $e->getMessage());
        }
    }

    // Create new customer in sevDesk
    private function createNewCustomerInSevDesk($sevDeskUrl, $sevDeskToken, $params)
    {
        try {
            $genderID = $params['customer']->id_gender;
            $gender = null;

            if (!$params['customer']->company) {
                if ($genderID === 1) {
                    $gender = Configuration::get('MY_SYNC_SEVDESK_MALE_TITLE', '');
                } else if ($genderID === 2) {
                    $gender = Configuration::get('MY_SYNC_SEVDESK_FEMALE_TITLE', '');
                }else{
                    $gender = Configuration::get('MY_SYNC_SEVDESK_NEUTRAL_TITLE', '');
                }
            }
            $customerData = [
                "name" => $params['customer']->company,
                "status" => 1000,
                "customerNumber" => null,
                "parent" => null,
                "surename" => $params['customer']->firstname,
                "familyname" => $params['customer']->lastname,
                "titel" => null,
                "category" => [
                    "id" => 3,
                    "objectName" => "Category"
                ],
                "description" => null,
                "academicTitle" => null,
                "gender" => $gender,
                "name2" => null,
                "birthday" => $params['customer']->birthday,
                "vatNumber" => null,
                "bankAccount" => null,
                "bankNumber" => null,
                "defaultCashbackTime" => 0,
                "defaultCashbackPercent" => 0,
                "defaultTimeToPay" => 0,
                "taxNumber" => null,
                "taxOffice" => null,
                "exemptVat" => true,
                "taxType" => "default",
                "taxSet" => null,
                "defaultDiscountAmount" => 0,
                "defaultDiscountPercentage" => true,
                "buyerReference" => null,
                "governmentAgency" => false,
                "customFieldSetting" => [
                    "ps_contact_id" => $params['customer']->id
                ]
            ];

            $ch = curl_init($sevDeskUrl . 'Contact');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $sevDeskToken,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($customerData));

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception('cURL error: ' . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode != 200 && $httpCode != 201) {
                throw new Exception("HTTP error: Status $httpCode");
            }

            $responseData = json_decode($response, true);
            if (!$responseData || !isset($responseData['objects']['id'])) {
                throw new Exception('Invalid response from SevDesk API');
            }

            return json_decode($response, true)['objects']['id'];

        } catch (Exception $e) {
            $this->addLog('Error during creating new customer in SevDesk: ' . $e->getMessage());
            return null;
        } finally {
            curl_close($ch);
        }
    }

    // Create new invoice in sevDesk
    private function createNewInvoiceInSevDesk($sevDeskUrl, $sevDeskToken, $params, $sevDeskContactId)
    {
        try {
            // URL
            $this->sevDeskUrl = Configuration::get('MY_SYNC_SEVDESK_API_URL');
            // Current Date
            $currentDate = date('Y-m-d');
            // Delivery Date Goal (Current date + Configuration::get('MY_SYNC_SEVDESK_DAYS_UNTIL_DELIVERY'))
            $daysUntilDelivery = Configuration::get('MY_SYNC_SEVDESK_DAYS_UNTIL_DELIVERY') ?? 0;
            $deliveryDateGoal = date('d.m.y', strtotime($currentDate . ' + ' . $daysUntilDelivery . ' days'));

            // Extract address details
            $shippingAddressId = $params['order']->id_address_delivery;
            $shippingAddress = new Address((int)$shippingAddressId);
            $billingAddressId = $params['order']->id_address_invoice;
            $billingAddress = new Address((int)$billingAddressId);

            $shippingAddressLine = $shippingAddress->address1 . ' ' . $shippingAddress->address2;
            $shippingCity = $shippingAddress->city;
            $shippingZip = $shippingAddress->postcode;

            $billingAddressLine = $billingAddress->address1 . ' ' . $billingAddress->address2;
            $billingCity = $billingAddress->city;
            $billingZip = $billingAddress->postcode;

            // Get all countries from sevdesk and find the id of the country for the post request
            $shippingCountryId = null;
            $shippingCountryCode = Country::getIsoById($shippingAddress->id_country);
            $billingCountryId = null;
            $billingCountryCode = Country::getIsoById($billingAddress->id_country);
            $staticCountries = $this->getSevDeskCountries($sevDeskUrl, $sevDeskToken);

            if(!isset($staticCountries)){
                throw new Exception('No countries found in sevDesk');
            }

            foreach ($staticCountries as $staticCountry) {

                if(!isset($staticCountry['code'])){
                    continue;
                }

                if ($staticCountry['code'] === strtolower($shippingCountryCode)) {
                    $shippingCountryId = $staticCountry['id'];
                }
                if ($staticCountry['code'] === strtolower($billingCountryCode)) {
                    $billingCountryId = $staticCountry['id'];
                }
            }

            if(!isset($billingCountryId)){
                throw new Exception('No billing country found in sevDesk');
            }

            // Create Addresses
            $billingAddressResponse = $this->createNewContactAddressInSevDesk($sevDeskUrl, $sevDeskToken, $sevDeskContactId, $params['customer']->firstname . ' ' . $params['customer']->lastname, $billingAddressLine, $billingCity, $billingZip, $billingCountryId);
            if(!isset($billingAddressResponse)){
                throw new Exception('Failed to create new billing address in sevDesk');
            }
            if ($billingAddressId !== $shippingAddressId) {
                if(!isset($shippingCountryId)){
                    throw new Exception('No shipping country found in sevDesk');
                }
                $shippingAddressResponse = $this->createNewContactAddressInSevDesk($sevDeskUrl, $sevDeskToken, $sevDeskContactId, $params['customer']->firstname . ' ' . $params['customer']->lastname, $shippingAddressLine, $shippingCity, $shippingZip, $shippingCountryId);
                if(!isset($shippingAddressResponse)){
                    throw new Exception('Failed to create new shipping address in sevDesk');
                }
            }

            // Get all products from order and reformat it for sevDesk
            $order = $params['order'];
            $productList = $order->getProducts();

            $products = [];

            foreach ($productList as $product) {
                $products[] = [
                    "part" => null,
                    "quantity" => $product['product_quantity'] ?? null,
                    "price" => $product['product_price'] ?? null,
                    "name" => $product['product_name'] ?? null,
                    "unity" => [
                        "id" => 1,
                        "objectName" => "Unity"
                    ],
                    "positionNumber" => null,
                    "text" => null,
                    "discount" => null,
                    "taxRate" => $product['tax_rate'] ?? null,
                    "temporary" => null,
                    "priceGross" => $product['product_price_wt'] ?? null,
                    "priceTax" => null,
                    "mapAll" => "true",
                    "objectName" => "InvoicePos"
                ];
            }

            $sevUserId = $this->getSevUserId($sevDeskUrl, $sevDeskToken);
            if(!isset($sevUserId) || $sevUserId == -1){
                throw new Exception('No sevDesk user found');
            }

            // Reformat invoice data for sevDesk
            $invoiceData = [
                "invoice" => [
                    "invoiceNumber" => null,
                    "contact" => [
                        "id" => (int)$sevDeskContactId,
                        "objectName" => "Contact"
                    ],
                    "invoiceDate" => $currentDate,
                    "header" => null,
                    "headText" => null,
                    "footText" => null,
                    "timeToPay" => 0,
                    "discountTime" => null,
                    "discount" => 0,
                    "addressName" => $params['customer']->company ?? $params['customer']->firstname . ' ' . $params['customer']->lastname,
                    "addressStreet" => $billingAddressLine,
                    "addressZip" => $billingZip,
                    "addressCity" => $billingCity,
                    "addressCountry" => [
                        "id" => $billingCountryId,
                        "objectName" => "StaticCountry"
                    ],
                    "payDate" => $currentDate,
                    "deliveryDate" => $deliveryDateGoal,
                    "status" => 200,
                    "smallSettlement" => 0,
                    "contactPerson" => [
                        "id" => $this->getSevUserId($sevDeskUrl, $sevDeskToken),
                        "objectName" => "SevUser"
                    ],
                    "taxRate" => 19,
                    "taxText" => "Umsatzsteuer 19%",
                    "dunningLevel" => null,
                    "addressParentName" => null,
                    "addressContactRef" => null,
                    "taxType" => "default",
                    "paymentMethod" => null,
                    "costCentre" => null,
                    "sendDate" => null,
                    "origin" => null,
                    "invoiceType" => "RE",
                    "accountIntervall" => null,
                    "accountLastInvoice" => null,
                    "accountNextInvoice" => null,
                    "reminderTotal" => null,
                    "reminderDebit" => null,
                    "reminderDeadline" => null,
                    "reminderCharge" => null,
                    "taxSet" => null,
                    "currency" => "EUR",
                    "entryType" => null,
                    "customerInternalNote" => null,
                    "showNet" => "1",
                    "enshrined" => null,
                    "sendType" => null,
                    "deliveryDateUntil" => null,
                    "datevConnectOnline" => null,
                    "sendPaymentReceivedNotificationDate" => null,
                    "mapAll" => "true",
                    "objectName" => "Invoice"
                ],
                "invoicePosSave" => $products,
                "invoicePosDelete" => null,
                "discountSave" => [
                    [
                        "discount" => true,
                        "text" => Configuration::get('MY_SYNC_SEVDESK_DISCOUNT_TEXT', ''),
                        "percentage" => false,
                        "value" => $params['order']->total_discounts_tax_excl,
                        "objectName" => "Discounts",
                        "mapAll" => true
                    ]
                ],
                "discountDelete" => null,
                "takeDefaultAddress" => "true"
            ];

            $ch = curl_init($sevDeskUrl . 'Invoice/Factory/saveInvoice');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $sevDeskToken,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($invoiceData));

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception('cURL error: ' . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode != 200 && $httpCode != 201) {
                throw new Exception("HTTP error: Status $httpCode");
            }

            return $response;

        } catch (Exception $e) {
            $this->addLog('Error during creating new invoice in SevDesk: ' . $e->getMessage());
            return null;
        } finally {
            curl_close($ch);
        }
    }

    // Hook for Book invoice if order gets paid in prestashop
    public function hookActionPaymentConfirmation($params)
    {
        try {
            $this->sevDeskToken = Configuration::get('MY_SYNC_SEVDESK_INVOICES_API_TOKEN');
            $this->sevDeskUrl = Configuration::get('MY_SYNC_SEVDESK_API_URL');

            if (!isset($this->sevDeskToken)) {
                throw new Exception('No sevDesk token found');
            }

            if (!isset($this->sevDeskUrl)) {
                throw new Exception('No sevDesk url found');
            }

            $order_id = $params['id_order'];
            $amountPayed = $this->getTotalPaidAmountFromOrderId($order_id);

            if($amountPayed === null){
                throw new Exception('No payed amount found for ps_order with id: ' . $order_id);
            }

            $sevDeskInvoiceId = $this->getSevDeskInvoiceIdByPsOrderId($order_id);

            if ($sevDeskInvoiceId === null) {
                throw new Exception('No invoice found in sevDesk for ps_order with id: ' . $order_id);
            }

            $existingPaidAmount = $this->getExistingSevDeskPaidAmount($sevDeskInvoiceId);

            if($existingPaidAmount === null){
                throw new Exception('No existing paid amount found for sevDesk invoice with id: ' . $sevDeskInvoiceId);
            }

            $totalAmountPaid = (double)$amountPayed - (double)$existingPaidAmount;

            $paymentData = [
                "amount" => $totalAmountPaid,
                "date" => date('Y-m-d'),
                "type" => "N",
                "checkAccount" => [
                    "id" => (int)$this->getCheckAccountId($this->sevDeskUrl, $this->sevDeskToken),
                    "objectName" => "CheckAccount"
                ],
                "checkAccountTransaction" => null,
                "createFeed" => true
            ];

            $this->bookInvoice($this->sevDeskUrl, $this->sevDeskToken, $sevDeskInvoiceId, $paymentData);
            $this->addLog('Booked invoice with id: ' . $sevDeskInvoiceId . ' with amount: ' . (double)$totalAmountPaid);

        } catch (Exception $e) {
            $this->addLog('Error in hook actionPaymentConfirmation: ' . $e->getMessage());
        }
    }

    // Book invoice
    private function bookInvoice($sevDeskUrl, $sevDeskToken, $id, $paymentData)
    {
        try {
            $ch = curl_init($sevDeskUrl . 'Invoice/' . $id . '/bookAmount' . '?token=' . $sevDeskToken);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception('cURL error: ' . curl_error($ch));
            }
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode != 200 && $httpCode != 201) {
                throw new Exception("HTTP error: Status $httpCode");
            }

            return $response;
        } catch (Exception $e) {
            $this->addLog('Error during booking a payment for an invoice: ' . $e->getMessage());
            return null;
        } finally {
            curl_close($ch);
        }
    }

    // Get check account id
    private function getCheckAccountId($sevDeskUrl, $sevDeskToken)
    {
        try {
            $ch = curl_init($sevDeskUrl . 'CheckAccount' . '?token=' . $sevDeskToken);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPGET, true);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception('cURL error: ' . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode != 200 && $httpCode != 201) {
                throw new Exception("HTTP error: Status $httpCode");
            }

            $responseData = json_decode($response, true);
            if (!$responseData || !isset($responseData['objects'][0]['id'])) {
                throw new Exception('Invalid response from SevDesk API');
            }

            return $responseData['objects'][0]['id'];

        } catch (Exception $e) {
            $this->addLog('Error during checking SevDesk account id: ' . $e->getMessage());
            return null;
        } finally {
            curl_close($ch);
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

            $this->sevDeskUrl = Configuration::get('MY_SYNC_SEVDESK_API_URL');

            if (!isset($this->sevDeskUrl)) {
                throw new Exception('No sevDesk url found');
            }

            // Check if invoice exists
            $order_id = $params['id_order'];
            $sevDeskInvoiceId = $this->getSevDeskInvoiceIdByPsOrderId($order_id);
            if (!$sevDeskInvoiceId) {
                throw new Exception('No corresponding sevDesk invoice found for order ID: ' . $order_id);
            }

            $newOrderStatus = $params['newOrderStatus']->id;

            if ($newOrderStatus === 6) { // Canceled
                $cancelResponse = $this->cancelInvoice($order_id);
                if (!$cancelResponse) {
                    throw new Exception('Failed to cancel invoice in sevDesk for order ID: ' . $order_id);
                }
                $this->addLog($order_id . ': Order status changed to canceled');
            }
        } catch (Exception $e) {
            $this->addLog('Error in hook actionOrderStatusPostUpdate: ' . $e->getMessage());
        }
    }

    // Cancel invoice -> gets called if order gets canceled in prestashop
    private function cancelInvoice($id_order)
    {
        try {
            $sevDeskInvoiceId = $this->getSevDeskInvoiceIdByPsOrderId($id_order);
            $ch = curl_init($this->sevDeskUrl . 'Invoice/' . $sevDeskInvoiceId . '/cancelInvoice');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $this->sevDeskToken,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception('cURL error: ' . curl_error($ch));
            }
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode != 200 && $httpCode != 201) {
                throw new Exception("HTTP error: Status $httpCode");
            }

            return $response;
        } catch (Exception $e) {
            $this->addLog('Error canceling invoice: ' . $e->getMessage());
            return null;
        } finally {
            curl_close($ch);
        }
    }

    // Get Existing Sev Desk Invoice ID By PS Order ID
    private function getSevDeskInvoiceIdByPsOrderId($order_id)
    {
        $sql = 'SELECT id_sevdesk FROM `' . _DB_PREFIX_ . 'sevdesk_invoices` WHERE id_order = ' . $order_id;
        $result = Db::getInstance()->executeS($sql);
        return $result[0]['id_sevdesk'];
    }

    // Get total paid amount from order id in prestashop
    private function getTotalPaidAmountFromOrderId($order_id)
    {
        $sql = 'SELECT total_paid_tax_incl FROM `' . _DB_PREFIX_ . 'orders` WHERE id_order = ' . $order_id;
        $result = Db::getInstance()->executeS($sql);
        return $result[0]['total_paid_tax_incl'];
    }

    // Add new sevDesk invoice in DB
    public function addExistingSevDeskInvoiceToDb($id_prestashop_order, $id_sevdesk_invoice, $created_at = null, $updated_at = null): void
    {
        $columns = '`id_order`, `id_sevdesk`';
        $values = '\'' . pSQL($id_prestashop_order) . '\', \'' . pSQL($id_sevdesk_invoice) . '\'';

        if ($created_at !== null) {
            $columns .= ', `created_at`';
            $values .= ', \'' . pSQL($created_at) . '\'';
        }

        if ($updated_at !== null) {
            $columns .= ', `updated_at`';
            $values .= ', \'' . pSQL($updated_at) . '\'';
        }

        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'sevdesk_invoices` (' . $columns . ') VALUES (' . $values . ');';
        $this->executeSqlQuery($sql);
    }

    // Add new log in DB
    public function addLog($text, $created_at = null, $updated_at = null)
    {
        $text = $this->l($text);

        $columns = '`log`';
        $values = '\'' . pSQL($text) . '\'';

        if ($created_at !== null) {
            $columns .= ', `created_at`';
            $values .= ', \'' . pSQL($created_at) . '\'';
        }

        if ($updated_at !== null) {
            $columns .= ', `updated_at`';
            $values .= ', \'' . pSQL($updated_at) . '\'';
        }

        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'sevdesk_logs` (' . $columns . ') VALUES (' . $values . ');';
        return $this->executeSqlQuery($sql);
    }

    // Get all countries from sevdesk
    private function getSevDeskCountries($sevDeskUrl, $sevDeskToken)
    {
        try {
            $ch = curl_init($sevDeskUrl . 'StaticCountry' . '?token=' . $sevDeskToken);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPGET, true);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception('cURL error: ' . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode != 200 && $httpCode != 201) {
                throw new Exception("HTTP error: Status $httpCode");
            }

            return json_decode($response, true)['objects'];
        } catch (Exception $e) {
            $this->addLog('Error in retrieving SevDesk countries: ' . $e->getMessage());
            return null;
        } finally {
            curl_close($ch);
        }
    }

    // Create new address for customer in sevDesk
    private function createNewContactAddressInSevDesk($sevDeskUrl, $sevDeskToken, $sevDeskContactId, $name, $addressLine, $city, $zip, $countryId)
    {
        try {
            $addressData = [
                "contact" => [
                    "id" => (int)$sevDeskContactId,
                    "objectName" => "Contact"
                ],
                "name" => $name,
                "street" => $addressLine,
                "zip" => $zip,
                "city" => $city,
                "country" => [
                    "id" => $countryId,
                    "objectName" => "StaticCountry"
                ],
                "category" => null,
            ];

            $ch = curl_init($sevDeskUrl . 'ContactAddress');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $sevDeskToken,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($addressData));

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception('cURL error: ' . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode != 200 && $httpCode != 201) {
                throw new Exception("HTTP error: Status $httpCode");
            }

            return json_decode($response, true);

        } catch (Exception $e) {
            $this->addLog('Error in creating new contact addresses in SevDesk: ' . $e->getMessage());
            return null;
        } finally {
            curl_close($ch);
        }
    }

    // Get sevDesk sevUser id
    private function getSevUserId($sevDeskUrl, $sevDeskToken): int
    {
        try {
            $ch = curl_init($sevDeskUrl . 'SevUser' . '?token=' . $sevDeskToken);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPGET, true);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception('cURL error: ' . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode != 200 && $httpCode != 201) {
                throw new Exception("HTTP error: Status $httpCode");
            }

            $responseData = json_decode($response, true);
            if (!$responseData || !isset($responseData['objects'][0]['id'])) {
                throw new Exception('Invalid response from SevDesk API');
            }

            return (int)$responseData['objects'][0]['id'];

        } catch (Exception $e) {
            $this->addLog('Error in getting SevDesk SevUser id: ' . $e->getMessage());
            return -1;
        } finally {
            curl_close($ch);
        }
    }

    // Get sevDesk previously total paid amount
    private function getExistingSevDeskPaidAmount($sevDeskInvoiceId)
    {
        try {
            $ch = curl_init($this->sevDeskUrl . 'Invoice/' . $sevDeskInvoiceId . '?token=' . $this->sevDeskToken);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPGET, true);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception('cURL error: ' . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode != 200 && $httpCode != 201) {
                throw new Exception("HTTP error: Status $httpCode");
            }

            $responseData = json_decode($response, true);
            if (!$responseData || !isset($responseData['objects'][0]['paidAmount'])) {
                throw new Exception('Invalid response from SevDesk API for invoice ID: ' . $sevDeskInvoiceId);
            }

            return $responseData['objects'][0]['paidAmount'];

        } catch (Exception $e) {
            $this->addLog('Error during fetching existing SevDesk paid amount: ' . $e->getMessage());
            return 0;
        } finally {
            curl_close($ch);
        }
    }

    // Execute SQL Query
    private function executeSqlQuery($sql) {
        return Db::getInstance()->execute($sql);
    }


}
