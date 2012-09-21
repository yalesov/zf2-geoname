<?php

namespace Heartsentwined\Geoname\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Heartsentwined\Geoname\Entity\Timezone
 */
class Timezone
{
    /**
     * @var integer $id
     */
    private $id;

    /**
     * @var string $code
     */
    private $code;

    /**
     * @var \Doctrine\Common\Collections\ArrayCollection
     */
    private $places;

    /**
     * @var Heartsentwined\Geoname\Entity\Country
     */
    private $country;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->places = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
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
     * Set code
     *
     * @param string $code
     * @return Timezone
     */
    public function setCode($code)
    {
        $this->code = $code;
    
        return $this;
    }

    /**
     * Get code
     *
     * @return string 
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Add places
     *
     * @param Heartsentwined\Geoname\Entity\Place $places
     * @return Timezone
     */
    public function addPlace(\Heartsentwined\Geoname\Entity\Place $places)
    {
        $this->places[] = $places;
    
        return $this;
    }

    /**
     * Remove places
     *
     * @param Heartsentwined\Geoname\Entity\Place $places
     */
    public function removePlace(\Heartsentwined\Geoname\Entity\Place $places)
    {
        $this->places->removeElement($places);
    }

    /**
     * Get places
     *
     * @return Doctrine\Common\Collections\Collection 
     */
    public function getPlaces()
    {
        return $this->places;
    }

    /**
     * Set country
     *
     * @param Heartsentwined\Geoname\Entity\Country $country
     * @return Timezone
     */
    public function setCountry(\Heartsentwined\Geoname\Entity\Country $country = null)
    {
        $this->country = $country;
    
        return $this;
    }

    /**
     * Get country
     *
     * @return Heartsentwined\Geoname\Entity\Country 
     */
    public function getCountry()
    {
        return $this->country;
    }
}
