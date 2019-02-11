<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser\Model;

use JMS\Serializer\Annotation as JMS;

abstract class AbstractModel
{
    /**
     * @var int
     * @JMS\Type("integer")
     */
    private $parentProperty1;
}
