<?php

declare(strict_types=1);

namespace Tests\Tests\Liip\MetadataParser\Reducer;

use Liip\MetadataParser\Metadata\VersionRange;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\Reducer\OldestVersionReducer;
use PHPUnit\Framework\TestCase;

/**
 * @small
 */
class OldestVersionReducerTest extends TestCase
{
    public function testReduceUnversioned(): void
    {
        $property1 = new PropertyVariationMetadata('property1', false, true);
        $property1->setVersionRange(new VersionRange('1', '2'));
        $property2 = new PropertyVariationMetadata('property2', false, true);
        $property3 = new PropertyVariationMetadata('property3', false, true);

        $properties = [
            $property1,
            $property2,
            $property3,
        ];

        $reducedProperties = (new OldestVersionReducer())->reduce('property', $properties);
        $this->assertProperties(['property2', 'property3'], $reducedProperties);
    }

    public function testReduceOldest(): void
    {
        $property1 = new PropertyVariationMetadata('property1', false, true);
        $property1->setVersionRange(new VersionRange('3', '4'));
        $property2 = new PropertyVariationMetadata('property2', false, true);
        $property2->setVersionRange(new VersionRange('1', '2'));

        $properties = [
            $property1,
            $property2,
        ];

        $reducedProperties = (new OldestVersionReducer())->reduce('property', $properties);
        $this->assertProperties(['property2'], $reducedProperties);
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
