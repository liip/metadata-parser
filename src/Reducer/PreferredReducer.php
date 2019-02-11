<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Reducer;

use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;

/**
 * If there are preferred variants, limit to those.
 *
 * This is useful for ambiguous situations, e.g. when you have several versions
 * but serialize without a version.
 */
final class PreferredReducer implements PropertyReducerInterface
{
    public function reduce(string $serializedName, array $properties): array
    {
        $preferred = array_values(array_filter($properties, static function (PropertyVariationMetadata $property) {
            return $property->isPreferred();
        }));

        if (\count($preferred)) {
            return $preferred;
        }

        return $properties;
    }
}
