<?php

namespace Heartsentwined\Geoname\Entity;

/**
 * Heartsentwined\Geoname\Entity\Language
 */
class Language
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
     * @var string $iso3
     */
    private $iso3;

    /**
     * @var string $iso2
     */
    private $iso2;

    /**
     * @var string $iso1
     */
    private $iso1;

    /**
     * @var \Doctrine\Common\Collections\ArrayCollection
     */
    private $locales;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->locales = new \Doctrine\Common\Collections\ArrayCollection();
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
     * @param  string   $name
     * @return Language
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
     * Set iso3
     *
     * @param  string   $iso3
     * @return Language
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
     * @param  string   $iso2
     * @return Language
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
     * Set iso1
     *
     * @param  string   $iso1
     * @return Language
     */
    public function setIso1($iso1)
    {
        $this->iso1 = $iso1;

        return $this;
    }

    /**
     * Get iso1
     *
     * @return string
     */
    public function getIso1()
    {
        return $this->iso1;
    }

    /**
     * Add locales
     *
     * @param  Heartsentwined\Geoname\Entity\Locale $locales
     * @return Language
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
}
