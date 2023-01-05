<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser;

use JMS\Serializer\Annotation as JMS;
use Liip\MetadataParser\Metadata\PropertyTypeArray;
use Liip\MetadataParser\Metadata\PropertyTypePrimitive;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;

/**
 * @small
 */
class JMSParserTest extends AbstractJMSParserTest
{
    public function testReadOnlyProperty(): void
    {
        $c = new class() {
            /**
             * @JMS\ReadOnlyProperty()
             */
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('property', 1, $props[0]);
        $this->assertPropertyVariation('property', false, true, $props[0]->getVariations()[0]);
    }

    public function testAttributes()
    {
        $c = new class() {
            #[JMS\Type('string')]
            private $property1;

            #[JMS\Type('bool')]
            public $property2;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(2, $props, 'Number of properties should match');


        $this->assertPropertyCollection('property1', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertPropertyVariation('property1', false, false, $property);
        $this->assertPropertyType(PropertyTypePrimitive::class, 'string|null', true, $property->getType());

        $this->assertPropertyCollection('property2', 1, $props[1]);
        $property = $props[1]->getVariations()[0];
        $this->assertPropertyVariation('property2', true, false, $property);
        $this->assertPropertyType(PropertyTypePrimitive::class, 'bool|null', true, $property->getType());
    }

    public function testAttributesMixedWithAnnotations()
    {
        $c = new class() {
            /**
             * @JMS\SerializedName("property_mixed")
             * @JMS\Groups({"group1"})
             */
            #[JMS\Type('string')]
            private $mixedProperty;

            #[JMS\SerializedName('property_attribute')]
            #[JMS\Type('bool')]
            public $attributeProperty;

            /**
             * @JMS\Type("array<string>")
             */
            public $annotationsProperty;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(3, $props, 'Number of properties should match');

        $this->assertPropertyCollection('property_mixed', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertPropertyVariation('mixedProperty', false, false, $property);
        $this->assertPropertyType(PropertyTypePrimitive::class, 'string|null', true, $property->getType());
        $this->assertSame(['group1'], $props[0]->getVariations()[0]->getGroups());

        $this->assertPropertyCollection('property_attribute', 1, $props[1]);
        $property = $props[1]->getVariations()[0];
        $this->assertPropertyVariation('attributeProperty', true, false, $property);
        $this->assertPropertyType(PropertyTypePrimitive::class, 'bool|null', true, $property->getType());

        $this->assertPropertyCollection('annotations_property', 1, $props[2]);
        $property = $props[2]->getVariations()[0];
        $this->assertPropertyVariation('annotationsProperty', true, false, $property);
        $this->assertPropertyType(PropertyTypeArray::class, 'string[]|null', true, $property->getType());
    }

    public function testVirtualPropertyWithoutDocblock(): void
    {
        $c = new class() {
            #[JMS\VirtualProperty]
            public function foo(): string
            {
                return 'bar';
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('foo', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertPropertyVariation('foo', true, true, $property);
        $this->assertPropertyType(PropertyTypePrimitive::class, 'string', false, $property->getType());
        $this->assertPropertyAccessor('foo', null, $property->getAccessor());
    }
}
