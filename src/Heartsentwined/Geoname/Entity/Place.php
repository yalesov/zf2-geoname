<?php

namespace Heartsentwined\Geoname\Entity;

/**
 * Heartsentwined\Geoname\Entity\Place
 */
class Place
{
    /**
     * @var integer $id
     */
    private $id;

    /**
     * @var string $name
     */
    private $name;

    /**
     * @var float $latitude
     */
    private $latitude;

    /**
     * @var float $longitude
     */
    private $longitude;

    /**
     * @var integer $elevation
     */
    private $elevation;

    /**
     * @var integer $digiEleModel
     */
    private $digiEleModel;

    /**
     * @var integer $population
     */
    private $population;

    /**
     * @var string $countryCode
     */
    private $countryCode;

    /**
     * @var string $admin1Code
     */
    private $admin1Code;

    /**
     * @var string $admin2Code
     */
    private $admin2Code;

    /**
     * @var string $admin3Code
     */
    private $admin3Code;

    /**
     * @var string $admin4Code
     */
    private $admin4Code;

    /**
     * @var boolean $isDeprecated
     */
    private $isDeprecated;

    /**
     * @var Heartsentwined\Geoname\Entity\Country
     */
    private $country;

    /**
     * @var \Doctrine\Common\Collections\ArrayCollection
     */
    private $children;

    /**
     * @var \Doctrine\Common\Collections\ArrayCollection
     */
    private $altNames;

    /**
     * @var \Doctrine\Common\Collections\ArrayCollection
     */
    private $countries;

    /**
     * @var Heartsentwined\Geoname\Entity\Place
     */
    private $parent;

    /**
     * @var Heartsentwined\Geoname\Entity\Feature
     */
    private $feature;

    /**
     * @var Heartsentwined\Geoname\Entity\Timezone
     */
    private $timezone;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->children = new \Doctrine\Common\Collections\ArrayCollection();
        $this->altNames = new \Doctrine\Common\Collections\ArrayCollection();
        $this->countries = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set id
     *
     * @param  integer $id
     * @return Place
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
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
     * Set name
     *
     * @param  string $name
     * @return Place
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set latitude
     *
     * @param  float $latitude
     * @return Place
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;

        return $this;
    }

    /**
     * Get latitude
     *
     * @return float
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Set longitude
     *
     * @param  float $longitude
     * @return Place
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;

        return $this;
    }

    /**
     * Get longitude
     *
     * @return float
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * Set elevation
     *
     * @param  integer $elevation
     * @return Place
     */
    public function setElevation($elevation)
    {
        $this->elevation = $elevation;

        return $this;
    }

    /**
     * Get elevation
     *
     * @return integer
     */
    public function getElevation()
    {
        return $this->elevation;
    }

    /**
     * Set digiEleModel
     *
     * @param  integer $digiEleModel
     * @return Place
     */
    public function setDigiEleModel($digiEleModel)
    {
        $this->digiEleModel = $digiEleModel;

        return $this;
    }

    /**
     * Get digiEleModel
     *
     * @return integer
     */
    public function getDigiEleModel()
    {
        return $this->digiEleModel;
    }

    /**
     * Set population
     *
     * @param  integer $population
     * @return Place
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
     * Set countryCode
     *
     * @param  string $countryCode
     * @return Place
     */
    public function setCountryCode($countryCode)
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    /**
     * Get countryCode
     *
     * @return string
     */
    public function getCountryCode()
    {
        return $this->countryCode;
    }

    /**
     * Set admin1Code
     *
     * @param  string $admin1Code
     * @return Place
     */
    public function setAdmin1Code($admin1Code)
    {
        $this->admin1Code = $admin1Code;

        return $this;
    }

    /**
     * Get admin1Code
     *
     * @return string
     */
    public function getAdmin1Code()
    {
        return $this->admin1Code;
    }

    /**
     * Set admin2Code
     *
     * @param  string $admin2Code
     * @return Place
     */
    public function setAdmin2Code($admin2Code)
    {
        $this->admin2Code = $admin2Code;

        return $this;
    }

    /**
     * Get admin2Code
     *
     * @return string
     */
    public function getAdmin2Code()
    {
        return $this->admin2Code;
    }

    /**
     * Set admin3Code
     *
     * @param  string $admin3Code
     * @return Place
     */
    public function setAdmin3Code($admin3Code)
    {
        $this->admin3Code = $admin3Code;

        return $this;
    }

    /**
     * Get admin3Code
     *
     * @return string
     */
    public function getAdmin3Code()
    {
        return $this->admin3Code;
    }

    /**
     * Set admin4Code
     *
     * @param  string $admin4Code
     * @return Place
     */
    public function setAdmin4Code($admin4Code)
    {
        $this->admin4Code = $admin4Code;

        return $this;
    }

    /**
     * Get admin4Code
     *
     * @return string
     */
    public function getAdmin4Code()
    {
        return $this->admin4Code;
    }

    /**
     * Set isDeprecated
     *
     * @param  boolean $isDeprecated
     * @return Place
     */
    public function setIsDeprecated($isDeprecated)
    {
        $this->isDeprecated = $isDeprecated;

        return $this;
    }

    /**
     * Get isDeprecated
     *
     * @return boolean
     */
    public function getIsDeprecated()
    {
        return $this->isDeprecated;
    }

    /**
     * Set country
     *
     * @param  Heartsentwined\Geoname\Entity\Country $country
     * @return Place
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

    /**
     * Add children
     *
     * @param  Heartsentwined\Geoname\Entity\Place $children
     * @return Place
     */
    public function addChildren(\Heartsentwined\Geoname\Entity\Place $children)
    {
        $this->children[] = $children;

        return $this;
    }

    /**
     * Remove children
     *
     * @param Heartsentwined\Geoname\Entity\Place $children
     */
    public function removeChildren(\Heartsentwined\Geoname\Entity\Place $children)
    {
        $this->children->removeElement($children);
    }

    /**
     * Get children
     *
     * @return Doctrine\Common\Collections\Collection
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Add altNames
     *
     * @param  Heartsentwined\Geoname\Entity\AltName $altNames
     * @return Place
     */
    public function addAltName(\Heartsentwined\Geoname\Entity\AltName $altNames)
    {
        $this->altNames[] = $altNames;

        return $this;
    }

    /**
     * Remove altNames
     *
     * @param Heartsentwined\Geoname\Entity\AltName $altNames
     */
    public function removeAltName(\Heartsentwined\Geoname\Entity\AltName $altNames)
    {
        $this->altNames->removeElement($altNames);
    }

    /**
     * Get altNames
     *
     * @return Doctrine\Common\Collections\Collection
     */
    public function getAltNames()
    {
        return $this->altNames;
    }

    /**
     * Add countries
     *
     * @param  Heartsentwined\Geoname\Entity\Country $countries
     * @return Place
     */
    public function addCountrie(\Heartsentwined\Geoname\Entity\Country $countries)
    {
        $this->countries[] = $countries;

        return $this;
    }

    /**
     * Remove countries
     *
     * @param Heartsentwined\Geoname\Entity\Country $countries
     */
    public function removeCountrie(\Heartsentwined\Geoname\Entity\Country $countries)
    {
        $this->countries->removeElement($countries);
    }

    /**
     * Get countries
     *
     * @return Doctrine\Common\Collections\Collection
     */
    public function getCountries()
    {
        return $this->countries;
    }

    /**
     * Set parent
     *
     * @param  Heartsentwined\Geoname\Entity\Place $parent
     * @return Place
     */
    public function setParent(\Heartsentwined\Geoname\Entity\Place $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent
     *
     * @return Heartsentwined\Geoname\Entity\Place
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Set feature
     *
     * @param  Heartsentwined\Geoname\Entity\Feature $feature
     * @return Place
     */
    public function setFeature(\Heartsentwined\Geoname\Entity\Feature $feature = null)
    {
        $this->feature = $feature;

        return $this;
    }

    /**
     * Get feature
     *
     * @return Heartsentwined\Geoname\Entity\Feature
     */
    public function getFeature()
    {
        return $this->feature;
    }

    /**
     * Set timezone
     *
     * @param  Heartsentwined\Geoname\Entity\Timezone $timezone
     * @return Place
     */
    public function setTimezone(\Heartsentwined\Geoname\Entity\Timezone $timezone = null)
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Get timezone
     *
     * @return Heartsentwined\Geoname\Entity\Timezone
     */
    public function getTimezone()
    {
        return $this->timezone;
    }
}
