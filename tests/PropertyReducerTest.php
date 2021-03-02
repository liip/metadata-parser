<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser;

use Liip\MetadataParser\Metadata\ParameterMetadata;
use Liip\MetadataParser\Metadata\VersionRange;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;
use Liip\MetadataParser\PropertyReducer;
use Liip\MetadataParser\Reducer\GroupReducer;
use Liip\MetadataParser\Reducer\TakeBestReducer;
use Liip\MetadataParser\Reducer\VersionReducer;
use PHPUnit\Framework\TestCase;

/**
 * @small
 */
class PropertyReducerTest extends TestCase
{
    public function testReduceEmpty(): void
    {
        $rawClassMetadata = new RawClassMetadata('Foo');

        $reduced = PropertyReducer::reduce($rawClassMetadata);

        $this->assertCount(0, $reduced->getProperties(), 'Number of properties should match');
        $this->assertCount(0, $reduced->getPostDeserializeMethods(), 'Number of post deserialize methods should match');
        $this->assertCount(0, $reduced->getConstructorParameters(), 'Number of constructor parameters should match');
    }

    public function testReduceKeepsPostDeserializeMethods(): void
    {
        $rawClassMetadata = new RawClassMetadata('Foo');
        $rawClassMetadata->addPostDeserializeMethod('method1');
        $rawClassMetadata->addPostDeserializeMethod('method2');

        $reduced = PropertyReducer::reduce($rawClassMetadata);

        $this->assertSame(['method1', 'method2'], $reduced->getPostDeserializeMethods());
    }

    public function testReduceKeepsConstructorParameters(): void
    {
        $rawClassMetadata = new RawClassMetadata('Foo');
        $rawClassMetadata->addConstructorParameter(new ParameterMetadata('param1', true, null));
        $rawClassMetadata->addConstructorParameter(new ParameterMetadata('param2', true, null));

        $reduced = PropertyReducer::reduce($rawClassMetadata);

        $this->assertCount(2, $reduced->getConstructorParameters(), 'Number of constructor parameters should match');
    }

    public function testReduceSimpleProperties(): void
    {
        $rawClassMetadata = new RawClassMetadata('Foo');
        $rawClassMetadata->addPropertyVariation('property1', new PropertyVariationMetadata('property1', false, true));
        $rawClassMetadata->addPropertyVariation('property2', new PropertyVariationMetadata('property2', false, true));

        $reduced = PropertyReducer::reduce($rawClassMetadata);

        $this->assertProperties(['property1', 'property2'], $reduced->getProperties());
    }

    public function testReducePropertiesWithReducers(): void
    {
        $property1 = new PropertyVariationMetadata('property1', false, true);
        $property1->setVersionRange(new VersionRange('1.0', '1.4'));
        $property1->setGroups(['group1']);
        $property2 = new PropertyVariationMetadata('property2', false, true);

        $rawClassMetadata = new RawClassMetadata('Foo');
        $rawClassMetadata->addPropertyVariation('property', $property1);
        $rawClassMetadata->addPropertyVariation('property', $property2);

        $reduced = PropertyReducer::reduce($rawClassMetadata, [
            new VersionReducer('1.0'),
            new GroupReducer(['group1']),
            new TakeBestReducer(),
        ]);

        $this->assertProperties(['property1'], $reduced->getProperties());
    }

    public function testReduceRemovesProperties(): void
    {
        $property1 = new PropertyVariationMetadata('property1', false, true);
        $property2 = new PropertyVariationMetadata('property2', false, true);
        $property2->setGroups(['group1']);
        $property3 = new PropertyVariationMetadata('property3', false, true);
        $property3->setGroups(['group1', 'group2']);

        $rawClassMetadata = new RawClassMetadata('Foo');
        $rawClassMetadata->addPropertyVariation('property1', $property1);
        $rawClassMetadata->addPropertyVariation('property2', $property2);
        $rawClassMetadata->addPropertyVariation('property3', $property3);

        $reduced = PropertyReducer::reduce($rawClassMetadata, [
            new VersionReducer('1.0'),
            new GroupReducer(['group1']),
            new TakeBestReducer(),
        ]);

        $this->assertProperties(['property2', 'property3'], $reduced->getProperties());
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
