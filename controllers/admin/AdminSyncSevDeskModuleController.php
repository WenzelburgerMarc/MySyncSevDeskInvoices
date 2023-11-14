<?php

class AdminSyncSevDeskModuleController extends ModuleAdminController
{


    public function initContent()
    {
        parent::initContent();

        $content = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'mysyncsevdeskinvoices/views/templates/admin/index.tpl');
        $this->context->smarty->assign([
            'content' => $this->content . $content, // without $this->content before $content it will only display the content from initContent()
        ]);
    }

    public function postProcess()
    {
        if (Tools::getValue('action') == 'sendTestInvoice') {
            $this->sendTestInvoiceToSevDesk();
        }
    }

    private function sendTestInvoiceToSevDesk()
    {
        $sevDeskUrl = 'https://my.sevdesk.de/api/v1/';
        $sevDeskToken = Configuration::get('MY_SYNC_SEVDESK_INVOICES_API_TOKEN');

        print_r($this->getContacts($sevDeskUrl, $sevDeskToken)); // Get All Contacts
        //print_r($this->getNextContactID($sevDeskUrl, $sevDeskToken)); // Get Next Contact ID
        //print_r($this->getNextInvoiceID($sevDeskUrl, $sevDeskToken)); // Get Next Invoice ID
        //print_r($this->getFirstContactId($sevDeskUrl, $sevDeskToken)); // Get First Contact For Testing Purpose
        //print_r($this->getSevUserId($sevDeskUrl, $sevDeskToken)); // Get Sev User ID
        //print_r($this->createInvoice($sevDeskUrl, $sevDeskToken)); // Create Invoice
        //print_r($this->checkAccountId($sevDeskUrl, $sevDeskToken)); // Get Check Account ID
    }

    private function getContacts($sevDeskUrl, $sevDeskToken)
    {
        $ch = curl_init($sevDeskUrl . 'Contact' . '?depth=1&token=' . $sevDeskToken);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);

        /* Result
         Array
(
    [objects] => Array
        (
            [0] => Array
                (
                    [id] => 68328668
                    [objectName] => Contact
                    [additionalInformation] =>
                    [create] => 2023-11-09T15:47:33+01:00
                    [update] => 2023-11-09T15:47:33+01:00
                    [name] => My Testcompany
                    [status] => 100
                    [customerNumber] => 1000
                    [surename] =>
                    [familyname] =>
                    [titel] =>
                    [category] => Array
                        (
                            [id] => 3
                            [objectName] => Category
                        )

                    [description] =>
                    [academicTitle] =>
                    [gender] =>
                    [sevClient] => Array
                        (
                            [id] => 871181
                            [objectName] => SevClient
                        )

                    [name2] =>
                    [birthday] =>
                    [vatNumber] =>
                    [bankAccount] =>
                    [bankNumber] =>
                    [defaultCashbackTime] =>
                    [defaultCashbackPercent] =>
                    [defaultTimeToPay] =>
                    [taxNumber] =>
                    [taxOffice] =>
                    [exemptVat] => 0
                    [taxType] =>
                    [defaultDiscountAmount] =>
                    [defaultDiscountPercentage] => 1
                    [buyerReference] =>
                    [governmentAgency] => 0
                    [defaultShowVat] => 1
                )

            [1] => Array
                (
                    [id] => 68325149
                    [objectName] => Contact
                    [additionalInformation] =>
                    [create] => 2023-11-09T14:56:40+01:00
                    [update] => 2023-11-09T14:56:40+01:00
                    [name] => My Testcompany
                    [status] => 100
                    [customerNumber] => 1337
                    [surename] =>
                    [familyname] =>
                    [titel] =>
                    [category] => Array
                        (
                            [id] => 3
                            [objectName] => Category
                        )

                    [description] =>
                    [academicTitle] =>
                    [gender] =>
                    [sevClient] => Array
                        (
                            [id] => 871181
                            [objectName] => SevClient
                        )

                    [name2] =>
                    [birthday] =>
                    [vatNumber] =>
                    [bankAccount] =>
                    [bankNumber] =>
                    [defaultCashbackTime] =>
                    [defaultCashbackPercent] =>
                    [defaultTimeToPay] =>
                    [taxNumber] =>
                    [taxOffice] =>
                    [exemptVat] => 0
                    [taxType] =>
                    [defaultDiscountAmount] =>
                    [defaultDiscountPercentage] => 1
                    [buyerReference] =>
                    [governmentAgency] => 0
                    [defaultShowVat] => 1
                )

        )

)

         */
    }

    private function getSevUserId($sevDeskUrl, $sevDeskToken): int
    {
        $ch = curl_init($sevDeskUrl . 'SevUser' . '?token=' . $sevDeskToken);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $response = curl_exec($ch);
        curl_close($ch);

        return (int)json_decode($response, true)['objects'][0]['id'];
    }

    private function getFirstContactId($sevDeskUrl, $sevDeskToken): int
    {
        $contacts = $this->getContacts($sevDeskUrl, $sevDeskToken);
        $firstContactId = $contacts['objects'][0]['id'];

        return $firstContactId;

        /* Result:
         * Array ( [id] => 68328668 [objectName] => Contact )
         */

    }

    private function checkIfContactAlreadyExists($sevDeskUrl, $sevDeskToken)
    {

    }

    private function getNextContactID($sevDeskUrl, $sevDeskToken)
    {
        $ch = curl_init($sevDeskUrl . 'Contact/Factory/getNextCustomerNumber' . '?token=' . $sevDeskToken);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true)['objects'];

        /* Result:
         * 1338
         */

    }

    private function createContact($sevDeskUrl, $sevDeskToken)
    {
        $contactArray = [
            'name' => 'string',
            'status' => 100,
            'customerNumber' => 'Customer-1337',
            'parent' => [
                'id' => 0,
                'objectName' => 'Contact'
            ],
            'surename' => 'John',
            'familyname' => 'Snow',
            'titel' => 'Commander',
            'category' => [
                'id' => 3,
                'objectName' => 'Category'
            ],
            'description' => 'Rightful king of the seven kingdoms',
            'academicTitle' => 'string',
            'gender' => 'string',
            'name2' => 'Targaryen',
            'birthday' => '2019-08-24',
            'vatNumber' => 'string',
            'bankAccount' => 'string',
            'bankNumber' => 'string',
            'defaultCashbackTime' => 0,
            'defaultCashbackPercent' => 0,
            'defaultTimeToPay' => 0,
            'taxNumber' => 'string',
            'taxOffice' => 'string',
            'exemptVat' => true,
            'taxType' => 'default',
            'taxSet' => [
                'id' => 0,
                'objectName' => 'string'
            ],
            'defaultDiscountAmount' => 0,
            'defaultDiscountPercentage' => true,
            'buyerReference' => 'string',
            'governmentAgency' => true,
            'customFieldSetting' => [
                'ps_contact_id' => 123
            ],
        ];

        $ch = curl_init($sevDeskUrl . 'Contact');

    }

    private function getNextInvoiceID($sevDeskUrl, $sevDeskToken): string
    {
        $ch = curl_init($sevDeskUrl . 'SevSequence/Factory/getByType?objectType=Invoice&type=RE' . '&token=' . $sevDeskToken);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $arr = json_decode($response, true);

        return (string)$arr['objects']['id'];

        /* Result:
         * 9112362
         */
    }

    private function checkIfInvoiceAlreadyExists($sevDeskUrl, $sevDeskToken)
    {

    }

    private function updateInvoice($sevDeskUrl, $sevDeskToken, $id)
    {

    }

    private function createInvoice($sevDeskUrl, $sevDeskToken)
    {
        $currentDate = date('Y-m-d');

        $invoiceData = [
            "invoice" => [
                "invoiceNumber" => null,//"RE-1000",
                "contact" => [
                    "id" => $this->getFirstContactId($sevDeskUrl, $sevDeskToken),
                    "objectName" => "Contact"
                ],
                "invoiceDate" => $currentDate,
                "header" => null,
                "headText" => null,
                "footText" => null,
                "timeToPay" => 0,
                "discountTime" => null,
                "discount" => 0,
                "addressName" => null,
                "addressStreet" => 'Martin-Luther-Ring 16',
                "addressZip" => '98574',
                "addressCity" => 'Schmalkalden',
                "addressCountry" => 'Deutschland',
                "payDate" => $currentDate,
                "deliveryDate" => $currentDate,
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
                "sendDate" => $currentDate,
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
                "address" => '',//"Martin-Luther-Ring 16\n98574 Schmalkalden\nDeutschland",
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
            "invoicePosSave" => [
                [
                    "part" => null,
                    "quantity" => 1,
                    "price" => 100,
                    "name" => "Dragonglass",
                    "priority" => 100,
                    "unity" => [
                        "id" => 1,
                        "objectName" => "Unity"
                    ],
                    "positionNumber" => null,
                    "text" => null,
                    "discount" => null,
                    "taxRate" => 19,
                    "temporary" => null,
                    "priceGross" => 100,
                    "priceTax" => null,
                    "mapAll" => "true",
                    "objectName" => "InvoicePos"
                ]
            ],
            "invoicePosDelete" => null,
            "discountSave" => [
                [
                    "discount" => true, // oder "true" als String, je nach API-Anforderung
                    "text" => "Rabattbeschreibung", // Beschreibung des Rabatts
                    "percentage" => true, // true für prozentualen Rabatt, false für festen Betrag
                    "value" => 10, // Rabattwert, z.B. 10% oder 10 Euro
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

        curl_close($ch);


        $createdInvoiceId = json_decode($response, true)['objects']['invoice']['id'];
        $totalAmountAfterDiscountAndTax = json_decode($response, true)['objects']['invoice']['sumGross'];


        $paymentData = [
            "amount" => (double)$totalAmountAfterDiscountAndTax,
            "date" => $currentDate,
            "type" => "N",
            "checkAccount" => [
                "id" => (int)$this->checkAccountId($sevDeskUrl, $sevDeskToken),
                "objectName" => "CheckAccount"
            ],
            "checkAccountTransaction" => null,
            "createFeed" => true // Behalten Sie true bei, um ein Feed-Element zu erstellen (falls von Ihrer API unterstützt)
        ];


        return $this->bookInvoice($sevDeskUrl, $sevDeskToken, $createdInvoiceId, $paymentData);
    }

    private function checkAccountId($sevDeskUrl, $sevDeskToken)
    {
        $ch = curl_init($sevDeskUrl . 'CheckAccount' . '?token=' . $sevDeskToken);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true)['objects'][0]['id'];
    }

    private function bookInvoice($sevDeskUrl, $sevDeskToken, $id, $paymentData)
    {
        $ch = curl_init($sevDeskUrl . 'Invoice/' . $id . '/bookAmount' . '?token=' . $sevDeskToken);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    private function getContactPerson($sevDeskUrl, $sevDeskToken)
    {

    }

}