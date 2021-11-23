<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser;

use Liip\MetadataParser\Exception\InvalidTypeException;
use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeArray;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\Metadata\PropertyTypePrimitive;
use Liip\MetadataParser\Metadata\PropertyTypeUnknown;
use Liip\MetadataParser\ModelParser\PhpDocParser;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyCollection;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;
use PHPUnit\Framework\TestCase;
use Tests\Liip\MetadataParser\ModelParser\Model\BaseModel;
use Tests\Liip\MetadataParser\ModelParser\Model\Nested;

/**
 * @small
 */
class PhpDocParserTest extends TestCase
{
    /**
     * @var PhpDocParser
     */
    private $parser;

    protected function setUp(): void
    {
        $this->parser = new PhpDocParser();
    }

    public function testEmpty(): void
    {
        $c = new class() {
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $this->assertSame(\get_class($c), $classMetadata->getClassName());
        $this->assertCount(0, $classMetadata->getPropertyCollections(), 'Number of properties should match');
    }

    public function testInvalidClass(): void
    {
        $classMetadata = new RawClassMetadata('__invalid__');

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('__invalid__');
        $this->parser->parse($classMetadata);
    }

    public function testInvalidType(): void
    {
        $c = new class() {
            /**
             * @var resource
             */
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(InvalidTypeException::class);
        $this->expectExceptionMessage('resource');
        $this->parser->parse($classMetadata);
    }

    public function provideProperty(): iterable
    {
        yield [
            new class() {
                private $property;
            },
            PropertyTypeUnknown::class,
            true,
            'mixed',
        ];

        yield [
            new class() {
                /**
                 * Test property with docblock but no type
                 */
                private $property;
            },
            PropertyTypeUnknown::class,
            true,
            'mixed',
        ];

        yield [
            new class() {
                private $property;
            },
            PropertyTypeUnknown::class,
            true,
            'mixed',
        ];

        yield [
            new class() {
                /**
                 * @var int|null
                 */
                private $property;
            },
            PropertyTypePrimitive::class,
            true,
            'int|null',
        ];

        yield [
            new class() {
                /**
                 * @var \stdClass|null
                 */
                private $property;
            },
            PropertyTypeClass::class,
            true,
            'stdClass|null',
        ];
    }

    /**
     * @dataProvider provideProperty
     */
    public function testProperty($c, string $propertyTypeClass, bool $nullable, string $type): void
    {
        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('property', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertProperty('property', false, false, $property);
        $this->assertPropertyType($propertyTypeClass, $type, $nullable, $property->getType());
    }

    public function testPrefilledClassMetadata(): void
    {
        $c = new class() {
            /**
             * @var string
             */
            private $property1;

            /**
             * @var int
             */
            private $property2;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $classMetadata->addPropertyVariation('foo', new PropertyVariationMetadata('property1', false, true));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(2, $props, 'Number of properties should match');

        $this->assertPropertyCollection('foo', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertProperty('property1', true, false, $property);
        $this->assertPropertyType(PropertyTypePrimitive::class, 'string', false, $property->getType());

        $this->assertPropertyCollection('property2', 1, $props[1]);
        $property = $props[1]->getVariations()[0];
        $this->assertProperty('property2', false, false, $property);
        $this->assertPropertyType(PropertyTypePrimitive::class, 'int', false, $property->getType());
    }

    public function testUpgradeArrayOfUnknown(): void
    {
        $c = new class() {
            /**
             * @var string[]
             */
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $propertyMetadata = new PropertyVariationMetadata('property', false, true);
        $propertyMetadata->setType(new PropertyTypeArray(new PropertyTypeUnknown(false), false, false));
        $classMetadata->addPropertyVariation('property', $propertyMetadata);
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('property', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertProperty('property', true, false, $property);
        $this->assertPropertyType(PropertyTypeArray::class, 'string[]', false, $property->getType());
    }

    public function testInheritedProperty(): void
    {
        $c = new class() extends BaseModel {
            /**
             * @var string
             */
            private $property1;

            /**
             * @var string
             */
            public $parentProperty2;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(3, $props, 'Number of properties should match');

        $this->assertPropertyCollection('parent_property1', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertProperty('parentProperty1', false, false, $property);
        $this->assertPropertyType(PropertyTypePrimitive::class, 'int', false, $property->getType());

        $this->assertPropertyCollection('parent_property2', 1, $props[1]);
        $property = $props[1]->getVariations()[0];
        $this->assertProperty('parentProperty2', false, false, $property);
        $this->assertPropertyType(PropertyTypePrimitive::class, 'string', false, $property->getType());

        $this->assertPropertyCollection('property1', 1, $props[2]);
        $property = $props[2]->getVariations()[0];
        $this->assertProperty('property1', false, false, $property);
        $this->assertPropertyType(PropertyTypePrimitive::class, 'string', false, $property->getType());
    }

    public function testNestedProperty(): void
    {
        $c = new class() {
            /**
             * @var Nested
             */
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('property', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertProperty('property', false, false, $property);
        $this->assertPropertyType(PropertyTypeClass::class, Nested::class, false, $property->getType());
    }

    private function assertPropertyCollection(string $serializedName, int $variations, PropertyCollection $prop): void
    {
        $this->assertSame($serializedName, $prop->getSerializedName(), 'Serialized name of property should match');
        $this->assertCount($variations, $prop->getVariations(), "Number of variations of property {$serializedName} should match");
    }

    private function assertProperty(string $name, bool $public, bool $readOnly, PropertyVariationMetadata $property): void
    {
        $this->assertSame($name, $property->getName(), 'Name of property should match');
        $this->assertSame($public, $property->isPublic(), "Public flag of property {$name} should match");
        $this->assertSame($readOnly, $property->isReadOnly(), "Read only flag of property {$name} should match");
    }

    private function assertPropertyType(string $propertyTypeClass, string $typeString, bool $nullable, PropertyType $type): void
    {
        $this->assertInstanceOf($propertyTypeClass, $type);
        $this->assertSame($nullable, $type->isNullable());
        $this->assertSame($typeString, (string) $type);
    }
}
