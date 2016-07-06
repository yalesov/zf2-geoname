<?php

namespace Yalesov\Geoname\Entity;

/**
 * Yalesov\Geoname\Entity\Feature
 */
class Feature
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
   * @var string $description
   */
  private $description;

  /**
   * @var string $comment
   */
  private $comment;

  /**
   * @var \Doctrine\Common\Collections\ArrayCollection
   */
  private $children;

  /**
   * @var \Doctrine\Common\Collections\ArrayCollection
   */
  private $places;

  /**
   * @var Yalesov\Geoname\Entity\Feature
   */
  private $parent;

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->children = new \Doctrine\Common\Collections\ArrayCollection();
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
   * @param  string  $code
   * @return Feature
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
   * Set description
   *
   * @param  string  $description
   * @return Feature
   */
  public function setDescription($description)
  {
    $this->description = $description;

    return $this;
  }

  /**
   * Get description
   *
   * @return string
   */
  public function getDescription()
  {
    return $this->description;
  }

  /**
   * Set comment
   *
   * @param  string  $comment
   * @return Feature
   */
  public function setComment($comment)
  {
    $this->comment = $comment;

    return $this;
  }

  /**
   * Get comment
   *
   * @return string
   */
  public function getComment()
  {
    return $this->comment;
  }

  /**
   * Add children
   *
   * @param  Yalesov\Geoname\Entity\Feature $children
   * @return Feature
   */
  public function addChildren(\Yalesov\Geoname\Entity\Feature $children)
  {
    $this->children[] = $children;

    return $this;
  }

  /**
   * Remove children
   *
   * @param Yalesov\Geoname\Entity\Feature $children
   */
  public function removeChildren(\Yalesov\Geoname\Entity\Feature $children)
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
   * Add places
   *
   * @param  Yalesov\Geoname\Entity\Place $places
   * @return Feature
   */
  public function addPlace(\Yalesov\Geoname\Entity\Place $places)
  {
    $this->places[] = $places;

    return $this;
  }

  /**
   * Remove places
   *
   * @param Yalesov\Geoname\Entity\Place $places
   */
  public function removePlace(\Yalesov\Geoname\Entity\Place $places)
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
   * Set parent
   *
   * @param  Yalesov\Geoname\Entity\Feature $parent
   * @return Feature
   */
  public function setParent(\Yalesov\Geoname\Entity\Feature $parent = null)
  {
    $this->parent = $parent;

    return $this;
  }

  /**
   * Get parent
   *
   * @return Yalesov\Geoname\Entity\Feature
   */
  public function getParent()
  {
    return $this->parent;
  }
}
