<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser\Model;

use JMS\Serializer\Annotation as JMS;

/**
 * @JMS\Discriminator(field = "type", map = {
 *     "ferry": "Tests\Liip\MetadataParser\ModelParser\Model\Ferry",
 *     "cabinCruiser": "Tests\Liip\MetadataParser\ModelParser\Model\CabinCruiser"
 * })
 */
abstract class Boat extends Vehicle
{
}
