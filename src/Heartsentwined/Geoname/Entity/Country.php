<?php

namespace Heartsentwined\Geoname\Entity;

/**
 * Heartsentwined\Geoname\Entity\Country
 */
class Country
{
    /**
     * @var integer $id
     */
    private $id;

    /**
     * @var string $iso3
     */
    private $iso3;

    /**
     * @var string $iso2
     */
    private $iso2;

    /**
     * @var string $isoNum
     */
    private $isoNum;

    /**
     * @var string $capital
     */
    private $capital;

    /**
     * @var integer $area
     */
    private $area;

    /**
     * @var integer $population
     */
    private $population;

    /**
     * @var string $tld
     */
    private $tld;

    /**
     * @var string $phone
     */
    private $phone;

    /**
     * @var string $postalCode
     */
    private $postalCode;

    /**
     * @var string $postalCodeRegex
     */
    private $postalCodeRegex;

    /**
     * @var Heartsentwined\Geoname\Entity\Place
     */
    private $place;

    /**
     * @var \Doctrine\Common\Collections\ArrayCollection
     */
    private $timezones;

    /**
     * @var Heartsentwined\Geoname\Entity\Currency
     */
    private $currency;

    /**
     * @var Heartsentwined\Geoname\Entity\Place
     */
    private $continent;

    /**
     * @var Heartsentwined\Geoname\Entity\Locale
     */
    private $mainLocale;

    /**
     * @var \Doctrine\Common\Collections\ArrayCollection
     */
    private $locales;

    /**
     * @var \Doctrine\Common\Collections\ArrayCollection
     */
    private $neighbours;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->timezones = new \Doctrine\Common\Collections\ArrayCollection();
        $this->locales = new \Doctrine\Common\Collections\ArrayCollection();
        $this->neighbours = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set iso3
     *
     * @param  string  $iso3
     * @return Country
     */
    public function setIso3($iso3)
    {
        $this->iso3 = $iso3;

        return $this;
    }

    /**
     * Get iso3
     *
     * @return string
     */
    public function getIso3()
    {
        return $this->iso3;
    }

    /**
     * Set iso2
     *
     * @param  string  $iso2
     * @return Country
     */
    public function setIso2($iso2)
    {
        $this->iso2 = $iso2;

        return $this;
    }

    /**
     * Get iso2
     *
     * @return string
     */
    public function getIso2()
    {
        return $this->iso2;
    }

    /**
     * Set isoNum
     *
     * @param  string  $isoNum
     * @return Country
     */
    public function setIsoNum($isoNum)
    {
        $this->isoNum = $isoNum;

        return $this;
    }

    /**
     * Get isoNum
     *
     * @return string
     */
    public function getIsoNum()
    {
        return $this->isoNum;
    }

    /**
     * Set capital
     *
     * @param  string  $capital
     * @return Country
     */
    public function setCapital($capital)
    {
        $this->capital = $capital;

        return $this;
    }

    /**
     * Get capital
     *
     * @return string
     */
    public function getCapital()
    {
        return $this->capital;
    }

    /**
     * Set area
     *
     * @param  integer $area
     * @return Country
     */
    public function setArea($area)
    {
        $this->area = $area;

        return $this;
    }

    /**
     * Get area
     *
     * @return integer
     */
    public function getArea()
    {
        return $this->area;
    }

    /**
     * Set population
     *
     * @param  integer $population
     * @return Country
     */
    public function setPopulation($population)
    {
        $this->population = $population;

        return $this;
    }

    /**
     * Get population
     *
     * @return integer
     */
    public function getPopulation()
    {
        return $this->population;
    }

    /**
     * Set tld
     *
     * @param  string  $tld
     * @return Country
     */
    public function setTld($tld)
    {
        $this->tld = $tld;

        return $this;
    }

    /**
     * Get tld
     *
     * @return string
     */
    public function getTld()
    {
        return $this->tld;
    }

    /**
     * Set phone
     *
     * @param  string  $phone
     * @return Country
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Get phone
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Set postalCode
     *
     * @param  string  $postalCode
     * @return Country
     */
    public function setPostalCode($postalCode)
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    /**
     * Get postalCode
     *
     * @return string
     */
    public function getPostalCode()
    {
        return $this->postalCode;
    }

    /**
     * Set postalCodeRegex
     *
     * @param  string  $postalCodeRegex
     * @return Country
     */
    public function setPostalCodeRegex($postalCodeRegex)
    {
        $this->postalCodeRegex = $postalCodeRegex;

        return $this;
    }

    /**
     * Get postalCodeRegex
     *
     * @return string
     */
    public function getPostalCodeRegex()
    {
        return $this->postalCodeRegex;
    }

    /**
     * Set place
     *
     * @param  Heartsentwined\Geoname\Entity\Place $place
     * @return Country
     */
    public function setPlace(\Heartsentwined\Geoname\Entity\Place $place = null)
    {
        $this->place = $place;

        return $this;
    }

    /**
     * Get place
     *
     * @return Heartsentwined\Geoname\Entity\Place
     */
    public function getPlace()
    {
        return $this->place;
    }

    /**
     * Add timezones
     *
     * @param  Heartsentwined\Geoname\Entity\Timezone $timezones
     * @return Country
     */
    public function addTimezone(\Heartsentwined\Geoname\Entity\Timezone $timezones)
    {
        $this->timezones[] = $timezones;

        return $this;
    }

    /**
     * Remove timezones
     *
     * @param Heartsentwined\Geoname\Entity\Timezone $timezones
     */
    public function removeTimezone(\Heartsentwined\Geoname\Entity\Timezone $timezones)
    {
        $this->timezones->removeElement($timezones);
    }

    /**
     * Get timezones
     *
     * @return Doctrine\Common\Collections\Collection
     */
    public function getTimezones()
    {
        return $this->timezones;
    }

    /**
     * Set currency
     *
     * @param  Heartsentwined\Geoname\Entity\Currency $currency
     * @return Country
     */
    public function setCurrency(\Heartsentwined\Geoname\Entity\Currency $currency = null)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Get currency
     *
     * @return Heartsentwined\Geoname\Entity\Currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set continent
     *
     * @param  Heartsentwined\Geoname\Entity\Place $continent
     * @return Country
     */
    public function setContinent(\Heartsentwined\Geoname\Entity\Place $continent = null)
    {
        $this->continent = $continent;

        return $this;
    }

    /**
     * Get continent
     *
     * @return Heartsentwined\Geoname\Entity\Place
     */
    public function getContinent()
    {
        return $this->continent;
    }

    /**
     * Set mainLocale
     *
     * @param  Heartsentwined\Geoname\Entity\Locale $mainLocale
     * @return Country
     */
    public function setMainLocale(\Heartsentwined\Geoname\Entity\Locale $mainLocale = null)
    {
        $this->mainLocale = $mainLocale;

        return $this;
    }

    /**
     * Get mainLocale
     *
     * @return Heartsentwined\Geoname\Entity\Locale
     */
    public function getMainLocale()
    {
        return $this->mainLocale;
    }

    /**
     * Add locales
     *
     * @param  Heartsentwined\Geoname\Entity\Locale $locales
     * @return Country
     */
    public function addLocale(\Heartsentwined\Geoname\Entity\Locale $locales)
    {
        $this->locales[] = $locales;

        return $this;
    }

    /**
     * Remove locales
     *
     * @param Heartsentwined\Geoname\Entity\Locale $locales
     */
    public function removeLocale(\Heartsentwined\Geoname\Entity\Locale $locales)
    {
        $this->locales->removeElement($locales);
    }

    /**
     * Get locales
     *
     * @return Doctrine\Common\Collections\Collection
     */
    public function getLocales()
    {
        return $this->locales;
    }

    /**
     * Add neighbours
     *
     * @param  Heartsentwined\Geoname\Entity\Country $neighbours
     * @return Country
     */
    public function addNeighbour(\Heartsentwined\Geoname\Entity\Country $neighbours)
    {
        $this->neighbours[] = $neighbours;

        return $this;
    }

    /**
     * Remove neighbours
     *
     * @param Heartsentwined\Geoname\Entity\Country $neighbours
     */
    public function removeNeighbour(\Heartsentwined\Geoname\Entity\Country $neighbours)
    {
        $this->neighbours->removeElement($neighbours);
    }

    /**
     * Get neighbours
     *
     * @return Doctrine\Common\Collections\Collection
     */
    public function getNeighbours()
    {
        return $this->neighbours;
    }
}
