<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Reducer;

use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;

/**
 * Select the property with the oldest version, or leave them alone if there is no property with version information.
 */
final class OldestVersionReducer implements PropertyReducerInterface
{
    /**
     * We keep track of properties with no lower version boundary and return
     * all of those if there are any, for other reducers to choose from them.
     */
    public function reduce(string $serializedName, array $properties): array
    {
        /** @var PropertyVariationMetadata|null $lowest */
        $lowest = null;
        $unversioned = [];
        /** @var PropertyVariationMetadata $property */
        foreach ($properties as $property) {
            if (null === $property->getVersionRange()->getSince()) {
                $unversioned[] = $property;
                continue;
            }
            if (null === $lowest || $property->getVersionRange()->allowsLowerThan($lowest->getVersionRange())) {
                $lowest = $property;
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
