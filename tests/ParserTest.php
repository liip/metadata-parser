<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser;

use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeArray;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\Metadata\PropertyTypePrimitive;
use Liip\MetadataParser\Metadata\PropertyTypeUnknown;
use Liip\MetadataParser\ModelParser\PhpDocParser;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyCollection;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\ModelParser\ReflectionParser;
use Liip\MetadataParser\Parser;
use PHPUnit\Framework\TestCase;
use Tests\Liip\MetadataParser\ModelParser\Model\Nested;

/**
 * @small
 */
class ParserTest extends TestCase
{
    /**
     * @var Parser
     */
    private $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser([
            new ReflectionParser(),
            new PhpDocParser(),
        ]);
    }

    public function testUnknownClass(): void
    {
        $this->expectException(ParseException::class);
        $this->parser->parse('__invalid__');
    }

    public function testSimple(): void
    {
        $c = new class() {
            private $property1;
            protected $property2;

            /**
             * @var string
             */
            public $property3;
        };

        $classMetadataList = $this->parser->parse(\get_class($c));

        $this->assertCount(1, $classMetadataList, 'Number of class metadata should match');

        $props = $classMetadataList[0]->getPropertyCollections();
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
        $this->assertPropertyType($property3->getType(), PropertyTypePrimitive::class, 'string', false);
    }

    public function testNested(): void
    {
        $c = new class() {
            /**
             * @var Nested
             */
            private $property;
        };

        $classMetadataList = $this->parser->parse(\get_class($c));

        $this->assertCount(2, $classMetadataList, 'Number of class metadata should match');

        // First class

        $props = $classMetadataList[0]->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of class metadata properties should match');

        $this->assertPropertyCollection('property', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertProperty('property', false, false, $property);
        $this->assertPropertyType($property->getType(), PropertyTypeClass::class, Nested::class, false);

        // Second class

        $props = $classMetadataList[1]->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of class metadata properties should match');

        $this->assertPropertyCollection('nested_property', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertProperty('nestedProperty', false, false, $property);
        $this->assertPropertyType($property->getType(), PropertyTypeUnknown::class, 'mixed', true);
    }

    public function testNestedArray(): void
    {
        $c = new class() {
            /**
             * @var Nested[]
             */
            private $property;
        };

        $classMetadataList = $this->parser->parse(\get_class($c));

        $this->assertCount(2, $classMetadataList, 'Number of class metadata should match');

        // First class

        $props = $classMetadataList[0]->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of class metadata properties should match');

        $this->assertPropertyCollection('property', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertProperty('property', false, false, $property);
        $this->assertPropertyType($property->getType(), PropertyTypeArray::class, Nested::class.'[]', false);
        $this->assertPropertyType($property->getType()->getSubType(), PropertyTypeClass::class, Nested::class, false);

        // Second class

        $props = $classMetadataList[1]->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of class metadata properties should match');

        $this->assertPropertyCollection('nested_property', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertProperty('nestedProperty', false, false, $property);
        $this->assertPropertyType($property->getType(), PropertyTypeUnknown::class, 'mixed', true);
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
}
