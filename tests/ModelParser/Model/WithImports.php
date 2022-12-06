<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser\Model;

use Doctrine\Common\Collections\Collection;
use Tests\Liip\MetadataParser\ModelParser\Model\BaseModel as Nested;
use Tests\Liip\MetadataParser\RecursionContextTest as ReflectionBaseModel;

class WithImports
{
    /**
     * @var ReflectionAbstractModel
     */
    private $sameNamespace;

    /**
     * @var ReflectionBaseModel
     */
    private $aliasDifferentNamespace;

    /**
     * @var Nested
     */
    private $aliasSameNamespace;

    /**
     * @var Collection<Nested>
     */
    private $collectionNamespace;
}
