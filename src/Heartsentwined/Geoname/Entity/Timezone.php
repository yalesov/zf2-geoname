<?php

namespace Heartsentwined\Geoname\Entity;

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
     * @var float $offset
     */
    private $offset;

    /**
     * @var float $offsetJan
     */
    private $offsetJan;

    /**
     * @var float $offsetJul
     */
    private $offsetJul;

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
     * @param  string   $code
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
     * Set offset
     *
     * @param  float    $offset
     * @return Timezone
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Get offset
     *
     * @return float
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Set offsetJan
     *
     * @param  float    $offsetJan
     * @return Timezone
     */
    public function setOffsetJan($offsetJan)
    {
        $this->offsetJan = $offsetJan;

        return $this;
    }

    /**
     * Get offsetJan
     *
     * @return float
     */
    public function getOffsetJan()
    {
        return $this->offsetJan;
    }

    /**
     * Set offsetJul
     *
     * @param  float    $offsetJul
     * @return Timezone
     */
    public function setOffsetJul($offsetJul)
    {
        $this->offsetJul = $offsetJul;

        return $this;
    }

    /**
     * Get offsetJul
     *
     * @return float
     */
    public function getOffsetJul()
    {
        return $this->offsetJul;
    }

    /**
     * Add places
     *
     * @param  Heartsentwined\Geoname\Entity\Place $places
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
     * @param  Heartsentwined\Geoname\Entity\Country $country
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
