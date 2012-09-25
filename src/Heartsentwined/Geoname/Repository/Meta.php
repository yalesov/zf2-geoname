<?php
namespace Heartsentwined\Geoname\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * Meta
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class Meta extends EntityRepository
{
    const STATUS_INSTALL_DOWNLOAD                   = 1;
    const STATUS_INSTALL_PREPARE                    = 2;
    const STATUS_INSTALL_LANGUAGE                   = 3;
    const STATUS_INSTALL_FEATURE                    = 4;
    const STATUS_INSTALL_PLACE                      = 5;
    const STATUS_INSTALL_COUNTRY_CURRENCY_LOCALE    = 6;
    const STATUS_INSTALL_TIMEZONE                   = 7;
    const STATUS_INSTALL_NEIGHBOUR                  = 8;
    const STATUS_INSTALL_PLACE_TIMEZONE             = 9;
    const STATUS_INSTALL_HIERARCHY                  = 10;
    const STATUS_INSTALL_ALT_NAME                   = 11;
    const STATUS_INSTALL_CLEANUP                    = 12;
    const STATUS_UPDATE                             = 99;
}
