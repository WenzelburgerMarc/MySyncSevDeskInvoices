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

        //print_r($this->getContacts($sevDeskUrl, $sevDeskToken)); // Get All Contacts
        //print_r($this->getNextContactID($sevDeskUrl, $sevDeskToken)); // Get Next Contact ID
        //print_r($this->getNextInvoiceID($sevDeskUrl, $sevDeskToken)); // Get Next Invoice ID
        //print_r($this->getFirstContactForCreatingInvoice($sevDeskUrl, $sevDeskToken)); // Get First Contact For Testing Purpose
        print_r($this->createInvoice($sevDeskUrl, $sevDeskToken)); // Create Invoice
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

    private function getFirstContactForCreatingInvoice($sevDeskUrl, $sevDeskToken)
    {
        $contacts = $this->getContacts($sevDeskUrl, $sevDeskToken);
        $firstContact = $contacts['objects'][0]['id'];

        return array(
            'id' => $firstContact,
            'objectName' => 'Contact'
        );

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

    }

    private function getNextInvoiceID($sevDeskUrl, $sevDeskToken)
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

        return $arr['objects']['id'];

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

        $invoiceData = [
            "invoice" => [
                "id" => $this->getNextInvoiceID($sevDeskUrl, $sevDeskToken),
                "objectName" => "Invoice",
                "invoiceNumber" => "RE-1000",
                "contact" => [
                    "id" => $this->getFirstContactForCreatingInvoice($sevDeskUrl, $sevDeskToken)['id'],
                    "objectName" => "Contact"
                ],
                "contactPerson" => [
                    "id" => 0,
                    "objectName" => "SevUser"
                ],
                "invoiceDate" => "01.01.2022",
                "header" => "Invoice RE-1000",
                "headText" => "header information",
                "footText" => "footer information",
                "timeToPay" => 20,
                "discount" => 0,
                "address" => "name\nstreet\npostCode city",
                "addressCountry" => [
                    "id" => 1,
                    "objectName" => "StaticCountry"
                ],
                "payDate" => "2019-08-24T14:15:22Z",
                "deliveryDate" => "01.01.2022",
                "deliveryDateUntil" => null,
                "status" => "100",
                "smallSettlement" => 0,
                "taxRate" => 19,
                "taxText" => "Umsatzsteuer 19%",
                "taxType" => "default",
                "taxSet" => null,
                "paymentMethod" => [
                    "id" => 21919,
                    "objectName" => "PaymentMethod"
                ],
                "sendDate" => "01.01.2020",
                "invoiceType" => "RE",
                "currency" => "EUR",
                "showNet" => "1",
                "sendType" => "VPR",
                "origin" => null,
                "customerInternalNote" => null,
                "mapAll" => true
            ],
            "invoicePosSave" => [
                [
                    "id" => null,
                    "objectName" => "InvoicePos",
                    "mapAll" => true,
                    "part" => [
                        "id" => 0,
                        "objectName" => "Part"
                    ],
                    "quantity" => 1,
                    "price" => 100,
                    "name" => "Dragonglass",
                    "unity" => [
                        "id" => 1,
                        "objectName" => "Unity"
                    ],
                    "positionNumber" => 0,
                    "text" => "string",
                    "discount" => 0,
                    "taxRate" => 19,
                    "priceGross" => 100,
                    "priceTax" => 0
                ]
            ],
            "invoicePosDelete" => null,
            "filename" => "string",
            "discountSave" => [
                [
                    "discount" => "true",
                    "text" => "string",
                    "percentage" => true,
                    "value" => 0,
                    "objectName" => "Discounts",
                    "mapAll" => "true"
                ]
            ],
            "discountDelete" => null
        ];


        $ch = curl_init($sevDeskUrl . 'Invoice/Factory/saveInvoice' . '?token=' . $sevDeskToken);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $sevDeskToken,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($invoiceData));

        $response = curl_exec($ch);

        curl_close($ch);

        return $response;
    }

    private function getContactPerson($sevDeskUrl, $sevDeskToken)
    {

    }

}