<?php

namespace Mysyncsevdeskinvoices\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="ps_sevdesk_logs")
 * @ORM\Entity()
 */
class Log
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id_log", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id_log;

    /**
     * @var string
     *
     * @ORM\Column(name="log", type="string", length=255)
     */
    private $log;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id_log;
    }

    public function setId($id)
    {
        $this->id_log = $id;
    }

    /**
     * @return string
     */
    public function getLog()
    {
        return $this->log;
    }

    public function setLog($log)
    {
        $this->log = $log;
    }


    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id_log' => $this->getId(),
            'log' => $this->getLog(),
        ];
    }
}