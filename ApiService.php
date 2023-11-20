<?php

class ApiService extends Module{

    // Hook Order From Customer Validated - actionValidateOrder
    // Create new customer in sevDesk
    public function createNewCustomerInSevDesk($databaseOperations, $sevDeskUrl, $sevDeskToken, $params)
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
            $databaseOperations->addLog('Error during creating new customer in SevDesk: ' . $e->getMessage());
            return null;
        } finally {
            curl_close($ch);
        }
    }

    // Create new invoice in sevDesk
    public function createNewInvoiceInSevDesk($databaseOperations, $sevDeskUrl, $sevDeskToken, $params, $sevDeskContactId)
    {
        try {
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
            $staticCountries = $this->getSevDeskCountries($databaseOperations, $sevDeskUrl, $sevDeskToken);

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

            // Use delivery or billing address?
            $useDelivery = Configuration::get('MY_SYNC_SEVDESK_USE_DELIVERY_ADDRESS');

            if ($useDelivery && $billingAddressId !== $shippingAddressId) {
                $shippingAddressResponse = $this->createNewContactAddressInSevDesk($databaseOperations, $sevDeskUrl, $sevDeskToken, $sevDeskContactId, $params['customer']->firstname . ' ' . $params['customer']->lastname, $shippingAddressLine, $shippingCity, $shippingZip, $shippingCountryId);
                if(!isset($shippingAddressResponse)){
                    throw new Exception('Failed to create new shipping address in sevDesk');
                }
            }

            $billingAddressResponse = $this->createNewContactAddressInSevDesk($databaseOperations, $sevDeskUrl, $sevDeskToken, $sevDeskContactId, $params['customer']->firstname . ' ' . $params['customer']->lastname, $billingAddressLine, $billingCity, $billingZip, $billingCountryId);
            if(!isset($billingAddressResponse)){
                throw new Exception('Failed to create new billing address in sevDesk');
            }

            if (!$useDelivery && $billingAddressId !== $shippingAddressId) {
                $shippingAddressResponse = $this->createNewContactAddressInSevDesk($databaseOperations, $sevDeskUrl, $sevDeskToken, $sevDeskContactId, $params['customer']->firstname . ' ' . $params['customer']->lastname, $shippingAddressLine, $shippingCity, $shippingZip, $shippingCountryId);
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

            $sevUserId = $this->getSevUserId($databaseOperations, $sevDeskUrl, $sevDeskToken);
            if(!isset($sevUserId) || $sevUserId == -1){
                throw new Exception('No sevDesk user found');
            }

            $name = $params['customer']->company ?? $params['customer']->firstname . ' ' . $params['customer']->lastname;
            $address = $name . '\n' . ($useDelivery == 1 ? $shippingAddressLine . '\n' . $shippingZip . ' ' . $shippingCity : $billingAddressLine . '\n' . $billingZip . ' ' . $billingCity);

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
                    "addressStreet" => $useDelivery == 1 ? $shippingAddressLine : $billingAddressLine,
                    "addressZip" => $useDelivery == 1 ? $shippingZip : $billingZip,
                    "addressCity" => $useDelivery == 1 ? $shippingCity : $billingCity,
                    "addressCountry" => [
                        "id" => $useDelivery == 1 ? $shippingCountryId : $billingCountryId,
                        "objectName" => "StaticCountry"
                    ],
                    "address"=> $address,
                    "payDate" => $currentDate,
                    "deliveryDate" => $deliveryDateGoal,
                    "status" => 200,
                    "smallSettlement" => 0,
                    "contactPerson" => [
                        "id" => $this->getSevUserId($databaseOperations, $sevDeskUrl, $sevDeskToken),
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
            $databaseOperations->addLog('Error during creating new invoice in SevDesk: ' . $e->getMessage());
            return null;
        } finally {
            curl_close($ch);
        }
    }

    // Get all countries from sevdesk
    private function getSevDeskCountries($databaseOperations, $sevDeskUrl, $sevDeskToken)
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
            $databaseOperations->addLog('Error in retrieving SevDesk countries: ' . $e->getMessage());
            return null;
        } finally {
            curl_close($ch);
        }
    }

    // Create new address for customer in sevDesk
    private function createNewContactAddressInSevDesk($databaseOperations, $sevDeskUrl, $sevDeskToken, $sevDeskContactId, $name, $addressLine, $city, $zip, $countryId)
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
            $databaseOperations->addLog('Error in creating new contact addresses in SevDesk: ' . $e->getMessage());
            return null;
        } finally {
            curl_close($ch);
        }
    }

    // Get sevDesk sevUser id
    private function getSevUserId($databaseOperations, $sevDeskUrl, $sevDeskToken): int
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
            $databaseOperations->addLog('Error in getting SevDesk SevUser id: ' . $e->getMessage());
            return -1;
        } finally {
            curl_close($ch);
        }
    }


    // Hook actionPaymentConfirmation
    // Book invoice
    public function bookInvoice($databaseOperations, $sevDeskUrl, $sevDeskToken, $id, $paymentData)
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
            $databaseOperations->addLog('Error during booking a payment for an invoice: ' . $e->getMessage());
            return null;
        } finally {
            curl_close($ch);
        }
    }

    // Get check account id
    public function getCheckAccountId($sevDeskUrl, $sevDeskToken)
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
            $this->databaseOperations->addLog('Error during checking SevDesk account id: ' . $e->getMessage());
            return null;
        } finally {
            curl_close($ch);
        }
    }

    // Get sevDesk previously total paid amount
    public function getExistingSevDeskPaidAmount($databaseOperations, $sevDeskInvoiceId, $sevDeskUrl, $sevDeskToken)
    {
        try {
            $ch = curl_init($sevDeskUrl . 'Invoice/' . $sevDeskInvoiceId . '?token=' . $sevDeskToken);
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
            $databaseOperations->addLog('Error during fetching existing SevDesk paid amount: ' . $e->getMessage());
            return 0;
        } finally {
            curl_close($ch);
        }
    }


    // Hook Order Status Changed - hookActionOrderStatusPostUpdate
    // Cancel invoice -> gets called if order gets canceled in prestashop
    public function cancelInvoice($databaseOperations, $id_order, $sevDeskUrl, $sevDeskToken)
    {
        try {
            $sevDeskInvoiceId = $databaseOperations->getSevDeskInvoiceIdByPsOrderId($id_order);
            $ch = curl_init($sevDeskUrl . 'Invoice/' . $sevDeskInvoiceId . '/cancelInvoice');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $sevDeskToken,
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
            $databaseOperations->addLog('Error canceling invoice: ' . $e->getMessage());
            return null;
        } finally {
            curl_close($ch);
        }
    }
}