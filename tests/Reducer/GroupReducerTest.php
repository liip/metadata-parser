<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\Reducer;

use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\Reducer\GroupReducer;
use PHPUnit\Framework\TestCase;

/**
 * @small
 */
class GroupReducerTest extends TestCase
{
    public function testReduce(): void
    {
        $property1 = new PropertyVariationMetadata('property1', false, true);
        $property2 = new PropertyVariationMetadata('property2', false, true);
        $property2->setGroups(['group1', 'group2']);
        $property3 = new PropertyVariationMetadata('property3', false, true);
        $property3->setGroups(['group3']);

        $properties = [
            $property1,
            $property2,
            $property3,
        ];

        $reducedProperties = (new GroupReducer(['group1']))->reduce('property', $properties);
        $this->assertProperties(['property2'], $reducedProperties);

        $reducedProperties = (new GroupReducer(['group2']))->reduce('property', $properties);
        $this->assertProperties(['property2'], $reducedProperties);

        $reducedProperties = (new GroupReducer(['group3']))->reduce('property', $properties);
        $this->assertProperties(['property3'], $reducedProperties);

        $reducedProperties = (new GroupReducer(['group1', 'group2', 'group3']))->reduce('property', $properties);
        $this->assertProperties(['property2', 'property3'], $reducedProperties);

        $reducedProperties = (new GroupReducer([]))->reduce('property', $properties);
        $this->assertProperties(['property1', 'property2', 'property3'], $reducedProperties);
    }

    /**
     * @param string[]                    $propertyNames
     * @param PropertyVariationMetadata[] $properties
     */
    private function assertProperties(array $propertyNames, iterable $properties): void
    {
        $names = [];
        foreach ($properties as $property) {
            $names[] = $property->getName();
        }

        $this->assertSame($propertyNames, $names, 'Properties should match');
    }
}
