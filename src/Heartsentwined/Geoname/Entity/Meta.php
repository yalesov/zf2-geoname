<?php

namespace Heartsentwined\Geoname\Entity;

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
     * @var boolean $isLocked
     */
    private $isLocked;

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
     * @param  integer $status
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
     * Set isLocked
     *
     * @param  boolean $isLocked
     * @return Meta
     */
    public function setIsLocked($isLocked)
    {
        $this->isLocked = $isLocked;

        return $this;
    }

    /**
     * Get isLocked
     *
     * @return boolean
     */
    public function getIsLocked()
    {
        return $this->isLocked;
    }
}
