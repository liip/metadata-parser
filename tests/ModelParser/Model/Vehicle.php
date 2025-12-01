<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser\Model;

use JMS\Serializer\Annotation as JMS;

/**
 * @JMS\Discriminator(field = "type", map = {
 *     "car": "Tests\Liip\MetadataParser\ModelParser\Model\Car",
 *     "moped": "Tests\Liip\MetadataParser\ModelParser\Model\Moped"
 * })
 */
abstract class Vehicle
{
}
