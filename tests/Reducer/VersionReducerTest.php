<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\Reducer;

use Liip\MetadataParser\Metadata\VersionRange;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\Reducer\VersionReducer;
use PHPUnit\Framework\TestCase;

/**
 * @small
 */
class VersionReducerTest extends TestCase
{
    public function testReduce(): void
    {
        $property1 = new PropertyVariationMetadata('property1', false, true);
        $property1->setVersionRange(new VersionRange(null, '1.4'));
        $property2 = new PropertyVariationMetadata('property2', false, true);
        $property2->setVersionRange(new VersionRange('2.0', '2.2'));
        $property3 = new PropertyVariationMetadata('property3', false, true);
        $property3->setVersionRange(new VersionRange('3.0', null));

        $properties = [
            $property1,
            $property2,
            $property3,
        ];

        $reducedProperties = (new VersionReducer('0.3'))->reduce('property', $properties);
        $this->assertProperties(['property1'], $reducedProperties);

        $reducedProperties = (new VersionReducer('1.0'))->reduce('property', $properties);
        $this->assertProperties(['property1'], $reducedProperties);

        $reducedProperties = (new VersionReducer('2.0'))->reduce('property', $properties);
        $this->assertProperties(['property2'], $reducedProperties);

        $reducedProperties = (new VersionReducer('3.0'))->reduce('property', $properties);
        $this->assertProperties(['property3'], $reducedProperties);

        $reducedProperties = (new VersionReducer('4.0'))->reduce('property', $properties);
        $this->assertProperties(['property3'], $reducedProperties);

        $reducedProperties = (new VersionReducer('1.9'))->reduce('property', $properties);
        $this->assertProperties([], $reducedProperties);
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
