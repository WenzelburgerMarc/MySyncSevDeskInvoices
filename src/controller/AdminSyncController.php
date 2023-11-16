<?php

namespace Mysyncsevdeskinvoices\Controller;

use Mysyncsevdeskinvoices\Entity\Invoice;
use Mysyncsevdeskinvoices\Entity\Log;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminSyncController extends FrameworkBundleAdminController
{

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

    public function getLogsData(){

    }

    public function getInvoicesData(){

    }

}