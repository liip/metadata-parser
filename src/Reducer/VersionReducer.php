<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Reducer;

/**
 * Select the property based on whether it is included in the specified version.
 */
final class VersionReducer implements PropertyReducerInterface
{
    /**
     * @var string
     */
    private $version;

    public function __construct(string $version)
    {
        $this->version = $version;
    }

    public function reduce(string $serializedName, array $properties): array
    {
        $includedProperties = [];
        foreach ($properties as $property) {
            if ($property->getVersionRange()->isIncluded($this->version)) {
                $includedProperties[] = $property;
            }
        }

        return $includedProperties;
    }
}
