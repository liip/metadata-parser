<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Reducer;

use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;

/**
 * Reorder the variants so that the property with the same name as the
 * serialized name of this property comes first.
 */
final class TakeBestReducer implements PropertyReducerInterface
{
    public function reduce(string $serializedName, array $properties): array
    {
        if (\count($properties) <= 1) {
            return $properties;
        }

        usort($properties, static function (PropertyVariationMetadata $propertyA, PropertyVariationMetadata $propertyB) use ($serializedName): int {
            if ($serializedName === $propertyA->getName()) {
                return -1;
            }
            if ($serializedName === $propertyB->getName()) {
                return 1;
            }

            return 0;
        });

        return $properties;
    }
}
