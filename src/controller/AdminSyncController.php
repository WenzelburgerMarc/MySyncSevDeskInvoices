<?php

namespace Mysyncsevdeskinvoices\Controller;

use Mysyncsevdeskinvoices\Entity\Invoice;
use Mysyncsevdeskinvoices\Entity\Log;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminSyncController extends FrameworkBundleAdminController
{

    public function getLogDataAction(){
        $logEntities = $this->getLogData();
        $logs = [];
        foreach ($logEntities as $entity) {
            $logs[] = [
                'id' => $entity->getId(),
                'log' => $entity->getLog(),
            ];
        }
        return new Response(json_encode($logs), 200, ['Content-Type' => 'application/json']);
    }


    public function getInvoiceDataAction(){
        $invoiceData = $this->getInvoiceData();
        $invoices = [];
        foreach ($invoiceData as $entity) {
            $invoices[] = [
                'id_sevdesk_invoice' => $entity->getId(),
                'id_order' => $entity->getOrderId(),
                'id_sevdesk' => $entity->getSevDeskId(),
            ];
        }
        return new Response(json_encode($invoices), 200, ['Content-Type' => 'application/json']);
    }

    public function getLogData(){
        return $this->getDoctrine()->getRepository(Log::class)->findAll();
    }

    public function getInvoiceData(){
        return $this->getDoctrine()->getRepository(Invoice::class)->findAll();
    }

    public function indexAction(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $invoiceEntities = $this->getInvoiceData();
        $invoices = [];

        foreach ($invoiceEntities as $entity) {
            $invoices[] = [
                'id_sevdesk_invoice' => $entity->getId(),
                'id_order' => $entity->getOrderId(),
                'id_sevdesk' => $entity->getSevDeskId(),
            ];
        }

        return $this->render('@Modules/mysyncsevdeskinvoices/templates/admin/links.html.twig',
            [
                'logs' => $this->getLogData(),
                'invoices' => $invoices
            ]
        );
    }

}