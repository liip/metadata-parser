<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Reducer;

use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;

/**
 * Select the property with the oldest version, or leave them alone if there is no property with version information.
 */
final class OldestVersionReducer implements PropertyReducerInterface
{
    public function reduce(string $serializedName, array $properties): array
    {
        $version = (string) PHP_INT_MAX;
        $lowest = null;
        $unversioned = [];
        /** @var PropertyVariationMetadata $property */
        foreach ($properties as $property) {
            if (null === $property->getVersion()->getSince()) {
                $unversioned[] = $property;
                continue;
            }
            if (version_compare($property->getVersion()->getSince(), $version, '<')) {
                $lowest = $property;
                $version = $property->getVersion()->getSince();
            }
        }

        if (\count($unversioned)) {
            return $unversioned;
        }

        if (null !== $lowest) {
            return [$lowest];
        }

        return $properties;
    }
}
