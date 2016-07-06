<?php

namespace Yalesov\Geoname\Entity;

/**
 * Yalesov\Geoname\Entity\Locale
 */
class Locale
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
   * @var Yalesov\Geoname\Entity\Language
   */
  private $language;

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
   * @param  string $code
   * @return Locale
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
   * Set language
   *
   * @param  Yalesov\Geoname\Entity\Language $language
   * @return Locale
   */
  public function setLanguage(\Yalesov\Geoname\Entity\Language $language = null)
  {
    $this->language = $language;

    return $this;
  }

  /**
   * Get language
   *
   * @return Yalesov\Geoname\Entity\Language
   */
  public function getLanguage()
  {
    return $this->language;
  }
}
