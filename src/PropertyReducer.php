<?php

declare(strict_types=1);

namespace Liip\MetadataParser;

use Liip\MetadataParser\Metadata\ClassMetadata;
use Liip\MetadataParser\Metadata\PropertyMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;
use Liip\MetadataParser\Reducer\PropertyReducerInterface;

abstract class PropertyReducer
{
    /**
     * @param PropertyReducerInterface[] $reducers
     */
    public static function reduce(RawClassMetadata $rawClassMetadata, array $reducers = []): ClassMetadata
    {
        $classProperties = [];

        foreach ($rawClassMetadata->getPropertyCollections() as $propertyCollection) {
            $properties = [];
            foreach ($propertyCollection->getVariations() as $property) {
                $properties[] = $property;
            }
            foreach ($reducers as $reducer) {
                $properties = $reducer->reduce($propertyCollection->getSerializedName(), $properties);
            }

            if (\count($properties) > 0) {
                $classProperties[] = PropertyMetadata::fromRawProperty($propertyCollection->getSerializedName(), $properties[0]);
            }
        }

        return ClassMetadata::fromRawClassMetadata($rawClassMetadata, $classProperties);
    }
}
