<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser\Model;

use JMS\Serializer\Annotation as JMS;

abstract class BaseModel extends AbstractModel
{
    /**
     * @var bool
     * @JMS\Type("bool")
     */
    protected $parentProperty2;
}
