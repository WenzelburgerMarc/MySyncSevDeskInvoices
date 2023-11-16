<?php

namespace Mysyncsevdeskinvoices\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="ps_sevdesk_invoices")
 * @ORM\Entity()
 */
class Invoice
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id_sevdesk_invoice", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id_sevdesk_invoice;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id_sevdesk_invoice;
    }

    public function setId($id)
    {
        $this->id_sevdesk_invoice = $id;
    }


    /**
     * @var int
     *
     * @ORM\Column(name="id_order", type="integer")
     */
    private $id_order;

    /**
     * @return int
     */
    public function getOrderId()
    {
        return $this->id_order;
    }

    public function setOrderId($id)
    {
        $this->id_order = $id;
    }

    /**
     * @var int
     *
     * @ORM\Column(name="id_sevdesk", type="integer")
     */
    private $id_sevdesk;

    /**
     * @return int
     */
    public function getSevDeskId()
    {
        return $this->id_sevdesk;
    }

    public function setSevDeskId($id)
    {
        $this->id_sevdesk = $id;
    }


    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id_sevdesk_invoice' => $this->getId(),
            'id_order' => $this->getOrderId(),
            'id_sevdesk' => $this->getSevDeskId(),

        ];
    }
}