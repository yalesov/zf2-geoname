<?php

namespace Heartsentwined\Geoname\Entity;

/**
 * Heartsentwined\Geoname\Entity\AltName
 */
class AltName
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
     * @var boolean $isPreferred
     */
    private $isPreferred;

    /**
     * @var boolean $isShort
     */
    private $isShort;

    /**
     * @var boolean $isColloquial
     */
    private $isColloquial;

    /**
     * @var boolean $isHistoric
     */
    private $isHistoric;

    /**
     * @var string $languageOther
     */
    private $languageOther;

    /**
     * @var boolean $isDeprecated
     */
    private $isDeprecated;

    /**
     * @var Heartsentwined\Geoname\Entity\Place
     */
    private $place;

    /**
     * @var Heartsentwined\Geoname\Entity\Language
     */
    private $language;

    /**
     * Set id
     *
     * @param  integer $id
     * @return AltName
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
     * @param  string  $name
     * @return AltName
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
     * Set isPreferred
     *
     * @param  boolean $isPreferred
     * @return AltName
     */
    public function setIsPreferred($isPreferred)
    {
        $this->isPreferred = $isPreferred;

        return $this;
    }

    /**
     * Get isPreferred
     *
     * @return boolean
     */
    public function getIsPreferred()
    {
        return $this->isPreferred;
    }

    /**
     * Set isShort
     *
     * @param  boolean $isShort
     * @return AltName
     */
    public function setIsShort($isShort)
    {
        $this->isShort = $isShort;

        return $this;
    }

    /**
     * Get isShort
     *
     * @return boolean
     */
    public function getIsShort()
    {
        return $this->isShort;
    }

    /**
     * Set isColloquial
     *
     * @param  boolean $isColloquial
     * @return AltName
     */
    public function setIsColloquial($isColloquial)
    {
        $this->isColloquial = $isColloquial;

        return $this;
    }

    /**
     * Get isColloquial
     *
     * @return boolean
     */
    public function getIsColloquial()
    {
        return $this->isColloquial;
    }

    /**
     * Set isHistoric
     *
     * @param  boolean $isHistoric
     * @return AltName
     */
    public function setIsHistoric($isHistoric)
    {
        $this->isHistoric = $isHistoric;

        return $this;
    }

    /**
     * Get isHistoric
     *
     * @return boolean
     */
    public function getIsHistoric()
    {
        return $this->isHistoric;
    }

    /**
     * Set languageOther
     *
     * @param  string  $languageOther
     * @return AltName
     */
    public function setLanguageOther($languageOther)
    {
        $this->languageOther = $languageOther;

        return $this;
    }

    /**
     * Get languageOther
     *
     * @return string
     */
    public function getLanguageOther()
    {
        return $this->languageOther;
    }

    /**
     * Set isDeprecated
     *
     * @param  boolean $isDeprecated
     * @return AltName
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
     * Set place
     *
     * @param  Heartsentwined\Geoname\Entity\Place $place
     * @return AltName
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
     * Set language
     *
     * @param  Heartsentwined\Geoname\Entity\Language $language
     * @return AltName
     */
    public function setLanguage(\Heartsentwined\Geoname\Entity\Language $language = null)
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Get language
     *
     * @return Heartsentwined\Geoname\Entity\Language
     */
    public function getLanguage()
    {
        return $this->language;
    }
}
