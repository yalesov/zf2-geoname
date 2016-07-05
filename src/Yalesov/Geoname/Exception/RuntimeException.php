<?php
namespace Yalesov\Geoname\Exception;

use Yalesov\Geoname\ExceptionInterface;

/**
 * RuntimeException
 *
 * @author yalesov <yalesov@cogito-lab.com>
 * @license GPL http://opensource.org/licenses/gpl-license.php
 */
class RuntimeException
    extends \RuntimeException
    implements ExceptionInterface
{
}
