<?php

namespace Heartsentwined\Geoname\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Heartsentwined\Geoname\Entity\Meta
 */
class Meta
{
    /**
     * @var integer $id
     */
    private $id;

    /**
     * @var integer $status
     */
    private $status;

    /**
     * @var boolean $lock
     */
    private $lock;


    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set status
     *
     * @param integer $status
     * @return Meta
     */
    public function setStatus($status)
    {
        $this->status = $status;
    
        return $this;
    }

    /**
     * Get status
     *
     * @return integer 
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set lock
     *
     * @param boolean $lock
     * @return Meta
     */
    public function setLock($lock)
    {
        $this->lock = $lock;
    
        return $this;
    }

    /**
     * Get lock
     *
     * @return boolean 
     */
    public function getLock()
    {
        return $this->lock;
    }
}
