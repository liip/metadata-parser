<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

use Doctrine\Common\Collections\Collection;

/**
 * @deprecated Please use {@see PropertyTypeIterable} instead
 */
final class PropertyTypeArray extends PropertyTypeIterable
{
    public function __construct(PropertyType $subType, bool $hashmap, bool $nullable, bool $isCollection = false)
    {
        parent::__construct($subType, $hashmap, $nullable, $isCollection ? Collection::class : null);
    }
}
