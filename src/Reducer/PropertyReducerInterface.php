<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Reducer;

use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;

interface PropertyReducerInterface
{
    /**
     * @param PropertyVariationMetadata[] $properties
     *
     * @return PropertyVariationMetadata[]
     */
    public function reduce(string $serializedName, array $properties): array;
}
