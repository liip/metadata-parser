<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser;

use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\Metadata\ParameterMetadata;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\Metadata\PropertyTypePrimitive;
use Liip\MetadataParser\Metadata\PropertyTypeUnknown;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyCollection;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;
use Liip\MetadataParser\ModelParser\ReflectionParser;
use PHPUnit\Framework\TestCase;
use Tests\Liip\MetadataParser\ModelParser\Fixtures\IntersectionTypeDeclarationModel;
use Tests\Liip\MetadataParser\ModelParser\Fixtures\TypeDeclarationModel;
use Tests\Liip\MetadataParser\ModelParser\Fixtures\UnionTypeDeclarationModel;
use Tests\Liip\MetadataParser\ModelParser\Model\ReflectionBaseModel;

/**
 * @small
 */
class ReflectionParserTest extends TestCase
{
    /**
     * @var ReflectionParser
     */
    private $parser;

    protected function setUp(): void
    {
        $this->parser = new ReflectionParser();
    }

    public function testUnknownClass(): void
    {
        $rawClassMetadata = new RawClassMetadata('__invalid__');

        $this->expectException(ParseException::class);
        $this->parser->parse($rawClassMetadata);
    }

    public function testEmpty(): void
    {
        $c = new class() {
        };

        $rawClassMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($rawClassMetadata);

        $this->assertSame(\get_class($c), $rawClassMetadata->getClassName());
        $this->assertCount(0, $rawClassMetadata->getPropertyCollections(), 'Number of class metadata properties should match');
    }

    public function testProperties(): void
    {
        $c = new class() {
            private $property1;
            protected $property2;
            public $property3;
        };

        $rawClassMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($rawClassMetadata);

        $props = $rawClassMetadata->getPropertyCollections();
        $this->assertCount(3, $props, 'Number of class metadata properties should match');

        $this->assertPropertyCollection('property1', 1, $props[0]);
        $property1 = $props[0]->getVariations()[0];
        $this->assertProperty('property1', false, false, $property1);
        $this->assertPropertyType($property1->getType(), PropertyTypeUnknown::class, 'mixed', true);

        $this->assertPropertyCollection('property2', 1, $props[1]);
        $property2 = $props[1]->getVariations()[0];
        $this->assertProperty('property2', false, false, $property2);
        $this->assertPropertyType($property2->getType(), PropertyTypeUnknown::class, 'mixed', true);

        $this->assertPropertyCollection('property3', 1, $props[2]);
        $property3 = $props[2]->getVariations()[0];
        $this->assertProperty('property3', true, false, $property3);
        $this->assertPropertyType($property3->getType(), PropertyTypeUnknown::class, 'mixed', true);
    }

    public function testTypedProperties(): void
    {
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            $this->markTestSkipped('Primitive property types are only supported in PHP 7.4 or newer');
        }

        $rawClassMetadata = new RawClassMetadata(TypeDeclarationModel::class);
        $this->parser->parse($rawClassMetadata);

        $props = $rawClassMetadata->getPropertyCollections();
        $this->assertCount(3, $props, 'Number of class metadata properties should match');

        $this->assertPropertyCollection('property1', 1, $props[0]);
        $property1 = $props[0]->getVariations()[0];
        $this->assertProperty('property1', false, false, $property1);
        $this->assertPropertyType($property1->getType(), PropertyTypePrimitive::class, 'string', false);

        $this->assertPropertyCollection('property2', 1, $props[1]);
        $property2 = $props[1]->getVariations()[0];
        $this->assertProperty('property2', true, false, $property2);
        $this->assertPropertyType($property2->getType(), PropertyTypePrimitive::class, 'int|null', true);

        $this->assertPropertyCollection('property3', 1, $props[2]);
        $property3 = $props[2]->getVariations()[0];
        $this->assertProperty('property3', false, false, $property3);
        $this->assertPropertyType($property3->getType(), PropertyTypeClass::class, ReflectionParserTest::class, false);
    }

    public function testTypedPropertiesUnion(): void
    {
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            $this->markTestSkipped('Union property types are only supported in PHP 8.0 or newer');
        }

        $rawClassMetadata = new RawClassMetadata(UnionTypeDeclarationModel::class);
        $this->parser->parse($rawClassMetadata);

        $props = $rawClassMetadata->getPropertyCollections();
        $this->assertCount(2, $props, 'Number of class metadata properties should match');

        $this->assertPropertyCollection('property1', 1, $props[0]);
        $property1 = $props[0]->getVariations()[0];
        $this->assertProperty('property1', false, false, $property1);
        $this->assertPropertyType($property1->getType(), PropertyTypeUnknown::class, 'mixed', true);

        $this->assertPropertyCollection('property2', 1, $props[1]);
        $property2 = $props[1]->getVariations()[0];
        $this->assertProperty('property2', true, false, $property2);
        $this->assertPropertyType($property2->getType(), PropertyTypeUnknown::class, 'mixed', true);
    }

    public function testTypedPropertiesIntersection(): void
    {
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Intersection property types are only supported in PHP 8.1 or newer');
        }

        $rawClassMetadata = new RawClassMetadata(IntersectionTypeDeclarationModel::class);
        $this->parser->parse($rawClassMetadata);

        $props = $rawClassMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of class metadata properties should match');

        $this->assertPropertyCollection('property1', 1, $props[0]);
        $property1 = $props[0]->getVariations()[0];
        $this->assertProperty('property1', false, false, $property1);
        // For now, just make sure we don't crash on intersection types. In context of serializing, we can probably not do anything meaningful with a intersection type anyways.
        $this->assertPropertyType($property1->getType(), PropertyTypeUnknown::class, 'mixed', true);
    }

    public function testPrefilledClassMetadata(): void
    {
        $c = new class() {
            private $property1;
            private $property2;
        };

        $rawClassMetadata = new RawClassMetadata(\get_class($c));
        $rawClassMetadata->addPropertyVariation('foo', new PropertyVariationMetadata('property1', false, true));
        $this->parser->parse($rawClassMetadata);

        $props = $rawClassMetadata->getPropertyCollections();
        $this->assertCount(2, $props, 'Number of class metadata properties should match');

        $this->assertPropertyCollection('foo', 1, $props[0]);
        $property1 = $props[0]->getVariations()[0];
        $this->assertProperty('property1', false, false, $property1);
        $this->assertPropertyType($property1->getType(), PropertyTypeUnknown::class, 'mixed', true);

        $this->assertPropertyCollection('property2', 1, $props[1]);
        $property2 = $props[1]->getVariations()[0];
        $this->assertProperty('property2', false, false, $property2);
        $this->assertPropertyType($property2->getType(), PropertyTypeUnknown::class, 'mixed', true);
    }

    public function testInheritedProperties(): void
    {
        $c = new class() extends ReflectionBaseModel {
            private $property1;
            public $parentProperty2;
        };

        $rawClassMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($rawClassMetadata);

        $props = $rawClassMetadata->getPropertyCollections();
        $this->assertCount(3, $props, 'Number of class metadata properties should match');

        $this->assertPropertyCollection('parent_property1', 1, $props[0]);
        $parentProperty1 = $props[0]->getVariations()[0];
        $this->assertProperty('parentProperty1', false, false, $parentProperty1);
        $this->assertPropertyType($parentProperty1->getType(), PropertyTypeUnknown::class, 'mixed', true);

        $this->assertPropertyCollection('parent_property2', 1, $props[1]);
        $parentProperty2 = $props[1]->getVariations()[0];
        $this->assertProperty('parentProperty2', true, false, $parentProperty2);
        $this->assertPropertyType($parentProperty2->getType(), PropertyTypeUnknown::class, 'mixed', true);

        $this->assertPropertyCollection('property1', 1, $props[2]);
        $property1 = $props[2]->getVariations()[0];
        $this->assertProperty('property1', false, false, $property1);
        $this->assertPropertyType($property1->getType(), PropertyTypeUnknown::class, 'mixed', true);
    }

    public function testConstructorParameters(): void
    {
        $c = new class('', 0, null) {
            public function __construct(string $foo, $bar, $baz = null)
            {
                implode(',', [$foo, $bar, $baz]);
            }
        };

        $rawClassMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($rawClassMetadata);

        $parameters = $rawClassMetadata->getConstructorParameters();
        $this->assertCount(3, $parameters, 'Number of constructor parameters should match');

        $this->assertParameter('foo', true, null, $parameters[0]);
        $this->assertParameter('bar', true, null, $parameters[1]);
        $this->assertParameter('baz', false, null, $parameters[2]);
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

    private function assertPropertyType(PropertyType $type, string $propertyTypeClass, string $typeString, bool $nullable): void
    {
        $this->assertInstanceOf($propertyTypeClass, $type);
        $this->assertSame($nullable, $type->isNullable());
        $this->assertSame($typeString, (string) $type);
    }

    private function assertParameter(string $name, bool $required, $defaultValue, ParameterMetadata $parameter): void
    {
        $this->assertSame($name, $parameter->getName(), 'Name of parameter should match');
        $this->assertSame($required, $parameter->isRequired(), "Required flag of parameter {$name} should match");

        if (!$required) {
            $this->assertSame($defaultValue, $parameter->getDefaultValue(), "Default value of parameter {$name} should match");
        }
    }
}
