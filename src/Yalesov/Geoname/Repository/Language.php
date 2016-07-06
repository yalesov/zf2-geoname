<?php
namespace Yalesov\Geoname\Repository;

use Doctrine\ORM\EntityRepository;
use Yalesov\ArgValidator\ArgValidator;

/**
 * Language
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class Language extends EntityRepository
{
  /**
   * findLanguage
   *
   * @param  string    $code iso 639 1- (?), 2- or 3- letter code
   * @return Language|null
   */
  public function findLanguage($code)
  {
    ArgValidator::assert($code, 'string');

    $dqb = $this->_em->createQueryBuilder();
    $dqb->select('l')
      ->from('Yalesov\Geoname\Entity\Language', 'l')
      ->where($dqb->expr()->orX(
        $dqb->expr()->eq('l.iso3', ':iso3'),
        $dqb->expr()->eq('l.iso2', ':iso2'),
        $dqb->expr()->eq('l.iso1', ':iso1')
      ))
      ->setParameters(array(
        'iso3' => $code,
        'iso2' => $code,
        'iso1' => $code,
      ))
      ->setMaxResults(1);

    if ($languages = $dqb->getQuery()->getResult()) {
      return current($languages);
    } else {
      return null;
    }
  }
}
