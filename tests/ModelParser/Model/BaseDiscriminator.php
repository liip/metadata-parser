<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser\Model;

use JMS\Serializer\Annotation as JMS;

/**
 * @JMS\Discriminator(field = "type", map = {"type1": "Tests\Liip\MetadataParser\ModelParser\Model\DiscriminatorWithFieldProperty"})
 */
abstract class BaseDiscriminator
{
}
