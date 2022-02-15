<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Doctrine\Common\Annotations\AnnotationReader;
use JMS\Serializer\Annotation as JMS;
use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\Metadata\PropertyAccessor;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeArray;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\Metadata\PropertyTypeDateTime;
use Liip\MetadataParser\Metadata\PropertyTypePrimitive;
use Liip\MetadataParser\Metadata\PropertyTypeUnknown;
use Liip\MetadataParser\ModelParser\JMSParser;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyCollection;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;
use PHPUnit\Framework\TestCase;
use Tests\Liip\MetadataParser\ModelParser\Model\BaseModel;
use Tests\Liip\MetadataParser\ModelParser\Model\Nested;

/**
 * @small
 *
 * We need 2 versions of this test to avoid parser errors about ReadOnly
 */
abstract class AbstractJMSParserTest extends TestCase
{
    /**
     * @var JMSParser
     */
    protected $parser;

    protected function setUp(): void
    {
        $this->parser = new JMSParser(new AnnotationReader());
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

    public function testInvalidClassAnnotations(): void
    {
        /**
         * @JMS\AccessType
         */
        $c = new class() {
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('AccessType');
        $this->parser->parse($classMetadata);
    }

    public function testUnsupportedClassAnnotations(): void
    {
        /**
         * @JMS\AccessType("public_method")
         */
        $c = new class() {
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('unsupported annotation');
        $this->parser->parse($classMetadata);
    }

    public function testClassXmlAnnotations(): void
    {
        /**
         * @JMS\XmlRoot("foo")
         */
        $c = new class() {
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');
        $this->assertPropertyCollection('property', 1, $props[0]);
    }

    public function providePropertyType(): iterable
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
                /**
                 * @JMS\Type("string")
                 */
                private $property;
            },
            PropertyTypePrimitive::class,
            true,
            'string|null',
        ];

        yield [
            new class() {
                /**
                 * @JMS\Type("integer")
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
                 * @JMS\Type("array<string>")
                 */
                private $property;
            },
            PropertyTypeArray::class,
            true,
            'string[]|null',
        ];
    }

    /**
     * @dataProvider providePropertyType
     */
    public function testPropertyType($c, string $propertyTypeClass, bool $nullable, string $type): void
    {
        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('property', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertPropertyVariation('property', false, false, $property);
        $this->assertPropertyType($propertyTypeClass, $type, $nullable, $property->getType());
    }

    public function testNestedProperty(): void
    {
        $c = new class() {
            /**
             * @JMS\Type("Tests\Liip\MetadataParser\ModelParser\Model\Nested")
             */
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('property', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertPropertyVariation('property', false, false, $property);
        $this->assertPropertyType(PropertyTypeClass::class, Nested::class.'|null', true, $property->getType());
    }

    public function testInvalidNestedClass(): void
    {
        if (!class_exists(NamedArgumentConstructor::class)) {
            $this->markTestSkipped('Before doctrine/annotations 1.12, the exception message is different');
        }
        $c = new class() {
            /**
             * @JMS\Type("__invalid__")
             */
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('unexpected "__" (T_UNKNOWN)');
        $this->parser->parse($classMetadata);
    }

    public function testInheritedProperty(): void
    {
        $c = new class() extends BaseModel {
            /**
             * @JMS\Type("string")
             */
            private $property1;

            /**
             * @var bool
             * @JMS\Type("bool")
             */
            public $parentProperty2;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(3, $props, 'Number of properties should match');

        $this->assertPropertyCollection('parent_property1', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertPropertyVariation('parentProperty1', false, false, $property);
        $this->assertPropertyType(PropertyTypePrimitive::class, 'int|null', true, $property->getType());

        $this->assertPropertyCollection('parent_property2', 1, $props[1]);
        $property = $props[1]->getVariations()[0];
        $this->assertPropertyVariation('parentProperty2', false, false, $property);
        $this->assertPropertyType(PropertyTypePrimitive::class, 'bool|null', true, $property->getType());

        $this->assertPropertyCollection('property1', 1, $props[2]);
        $property = $props[2]->getVariations()[0];
        $this->assertPropertyVariation('property1', false, false, $property);
        $this->assertPropertyType(PropertyTypePrimitive::class, 'string|null', true, $property->getType());
    }

    abstract public function testReadOnlyProperty(): void;

    public function testSerializedName(): void
    {
        $c = new class() {
            /**
             * @JMS\SerializedName("foo")
             */
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('foo', 1, $props[0]);
        $this->assertSame('property', $props[0]->getVariations()[0]->getName(), 'Name of property should match');
    }

    public function testSerializedNameTwice(): void
    {
        $c = new class() {
            /**
             * @JMS\SerializedName("foo")
             */
            private $property1;

            /**
             * @JMS\SerializedName("foo")
             */
            private $property2;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('foo', 2, $props[0]);
        $this->assertSame('property1', $props[0]->getVariations()[0]->getName(), 'Name of property should match');
        $this->assertSame('property2', $props[0]->getVariations()[1]->getName(), 'Name of property should match');
    }

    public function testSerializedNameMerge(): void
    {
        $c = new class() {
            /**
             * @JMS\SerializedName("links")
             * @JMS\Until("2")
             * @JMS\Accessor(getter="getLinks")
             */
            private $property;

            /**
             * @JMS\Since("3")
             */
            private $links;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('links', 2, $props[0]);
        $this->assertSame('property', $props[0]->getVariations()[0]->getName(), 'Name of property should match');
        $this->assertSame('links', $props[0]->getVariations()[1]->getName(), 'Name of property should match');
    }

    public function testSerializedNamePrefilled(): void
    {
        $c = new class() {
            /**
             * @JMS\SerializedName("foo")
             */
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $classMetadata->addPropertyVariation('property', new PropertyVariationMetadata('property', false, true));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('foo', 1, $props[0]);
        $this->assertSame('property', $props[0]->getVariations()[0]->getName(), 'Name of property should match');
    }

    public function testSerializedNamePrefilledMerge(): void
    {
        $c = new class() {
            /**
             * @JMS\SerializedName("links")
             * @JMS\Until("2")
             * @JMS\Accessor(getter="getLinks")
             */
            private $fakeLinks;

            /**
             * @JMS\Since("3")
             */
            private $links;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $classMetadata->addPropertyVariation('fakeLinks', new PropertyVariationMetadata('fakeLinks', false, true));
        $classMetadata->addPropertyVariation('links', new PropertyVariationMetadata('links', false, true));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('links', 2, $props[0]);
        $this->assertSame('fakeLinks', $props[0]->getVariations()[0]->getName(), 'Name of property should match');
        $this->assertSame('links', $props[0]->getVariations()[1]->getName(), 'Name of property should match');
    }

    public function testExclude(): void
    {
        $c = new class() {
            /**
             * @JMS\Exclude
             */
            private $property1;
            private $property2;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('property2', 1, $props[0]);
    }

    public function testExcludePrefilled(): void
    {
        $c = new class() {
            /**
             * @JMS\Exclude
             */
            private $property1;
            private $property2;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $classMetadata->addPropertyVariation('property1', new PropertyVariationMetadata('property1', false, true));
        $classMetadata->addPropertyVariation('property2', new PropertyVariationMetadata('property2', false, true));

        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('property2', 1, $props[0]);
    }

    public function testExcludePartial(): void
    {
        $c = new class() {
            /**
             * @JMS\SerializedName("foo")
             */
            private $property1;

            /**
             * @JMS\Exclude
             * @JMS\SerializedName("foo")
             */
            private $property2;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('foo', 1, $props[0]);
        $this->assertSame('property1', $props[0]->getVariations()[0]->getName(), 'Name of property should match');
    }

    public function testConditionalExclude(): void
    {
        $c = new class() {
            /**
             * @JMS\Exclude(if="foo")
             */
            private $property1;
            private $property2;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Exclude');
        $this->parser->parse($classMetadata);
    }

    public function testGroups(): void
    {
        $c = new class() {
            /**
             * @JMS\Groups({"group1", "group2"})
             */
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('property', 1, $props[0]);
        $this->assertSame(['group1', 'group2'], $props[0]->getVariations()[0]->getGroups());
    }

    public function testAccessor(): void
    {
        $c = new class() {
            private $property;

            /**
             * @JMS\Accessor(getter="getProperty")
             */
            private $propertyGet;

            /**
             * @JMS\Accessor(setter="setProperty")
             */
            private $propertySet;

            /**
             * @JMS\Accessor(getter="getProperty", setter="setProperty")
             */
            private $propertyGetSet;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(4, $props, 'Number of properties should match');

        $this->assertPropertyCollection('property', 1, $props[0]);
        $this->assertPropertyAccessor(null, null, $props[0]->getVariations()[0]->getAccessor());

        $this->assertPropertyCollection('property_get', 1, $props[1]);
        $this->assertPropertyAccessor('getProperty', null, $props[1]->getVariations()[0]->getAccessor());

        $this->assertPropertyCollection('property_set', 1, $props[2]);
        $this->assertPropertyAccessor(null, 'setProperty', $props[2]->getVariations()[0]->getAccessor());

        $this->assertPropertyCollection('property_get_set', 1, $props[3]);
        $this->assertPropertyAccessor('getProperty', 'setProperty', $props[3]->getVariations()[0]->getAccessor());
    }

    public function testVersionRange(): void
    {
        $c = new class() {
            private $property1;

            /**
             * @JMS\Since("1.2")
             */
            private $property2;

            /**
             * @JMS\Until("3.8")
             */
            private $property3;

            /**
             * @JMS\Since("4.0")
             * @JMS\Until("8.1")
             */
            private $property4;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(4, $props, 'Number of properties should match');

        $this->assertPropertyCollection('property1', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertTrue($property->getVersionRange()->isIncluded('1.0'));
        $this->assertTrue($property->getVersionRange()->isIncluded('3.8'));

        $this->assertPropertyCollection('property2', 1, $props[1]);
        $property = $props[1]->getVariations()[0];
        $this->assertFalse($property->getVersionRange()->isIncluded('1.0'));
        $this->assertTrue($property->getVersionRange()->isIncluded('2.2'));

        $this->assertPropertyCollection('property3', 1, $props[2]);
        $property = $props[2]->getVariations()[0];
        $this->assertTrue($property->getVersionRange()->isIncluded('2.2'));
        $this->assertFalse($property->getVersionRange()->isIncluded('4.0'));

        $this->assertPropertyCollection('property4', 1, $props[3]);
        $property = $props[3]->getVariations()[0];
        $this->assertFalse($property->getVersionRange()->isIncluded('3.9'));
        $this->assertTrue($property->getVersionRange()->isIncluded('4.0'));
        $this->assertTrue($property->getVersionRange()->isIncluded('8.1'));
        $this->assertFalse($property->getVersionRange()->isIncluded('8.2'));
    }

    public function testInvalidPropertyAnnotations(): void
    {
        $c = new class() {
            /**
             * @JMS\Type
             */
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Type');
        $this->parser->parse($classMetadata);
    }

    public function testPropertyXmlAnnotations(): void
    {
        $c = new class() {
            /**
             * @JMS\Type("string")
             * @JMS\XmlAttribute
             * @JMS\XmlKeyValuePairs
             * @JMS\XmlList
             * @JMS\XmlMap
             * @JMS\XmlValue
             */
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');
        $this->assertPropertyCollection('property', 1, $props[0]);
    }

    public function testUnsupportedPropertyAnnotations(): void
    {
        $c = new class() {
            /**
             * @JMS\Type("string")
             * @JMS\MaxDepth(1)
             */
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('unsupported annotation');
        $this->parser->parse($classMetadata);
    }

    public function testOrder(): void
    {
        /**
         * @JMS\AccessorOrder("custom", custom={"foo", "proPerty4", "proPerty1", "propPerty1"})
         */
        $c = new class() {
            private $proPerty1;
            /**
             * @JMS\SerializedName("foo")
             */
            private $proPerty2;
            private $proPerty3;
            private $proPerty4;
            private $proPerty5;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(5, $props, 'Number of properties should match');

        $this->assertPropertyCollection('pro_perty4', 1, $props[0]);
        $this->assertPropertyCollection('pro_perty1', 1, $props[1]);
        $this->assertPropertyCollection('foo', 1, $props[2]);
        $this->assertPropertyCollection('pro_perty3', 1, $props[3]);
        $this->assertPropertyCollection('pro_perty5', 1, $props[4]);
    }

    public function testOrderUnsupported(): void
    {
        /**
         * @JMS\AccessorOrder("foo")
         */
        $c = new class() {
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('AccessorOrder');
        $this->parser->parse($classMetadata);
    }

    public function testExclusionPolicy(): void
    {
        /**
         * @JMS\ExclusionPolicy("NONE")
         */
        $c = new class() {
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');
        $this->assertPropertyCollection('property', 1, $props[0]);
    }

    public function testExclusionPolicyUnsupported(): void
    {
        /**
         * @JMS\ExclusionPolicy("ALL")
         */
        $c = new class() {
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('ExclusionPolicy');
        $this->parser->parse($classMetadata);
    }

    public function testMethodWithoutDocBlock(): void
    {
        $c = new class() {
            public function foo(): string
            {
                return 'bar';
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(0, $props, 'Number of properties should match');
    }

    public function testVirtualProperty(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             */
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

    public function testVirtualPropertyWithName(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty("bar")
             */
            public function foo(): string
            {
                return 'bar';
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('bar', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertPropertyVariation('bar', true, true, $property);
        $this->assertPropertyType(PropertyTypePrimitive::class, 'string', false, $property->getType());
        $this->assertPropertyAccessor('foo', null, $property->getAccessor());
    }

    public function testVirtualPropertyWithReturnTypeHint(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             */
            public function getFoo(): int
            {
                return 0;
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('foo', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertPropertyType(PropertyTypePrimitive::class, 'int', false, $property->getType());
    }

    public function testVirtualPropertyWithReturnDocBlock(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             *
             * @return string[]
             */
            public function getFoo(): array
            {
                return [];
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('foo', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertPropertyType(PropertyTypeArray::class, 'string[]', false, $property->getType());
    }

    public function testVirtualPropertyWithConflictingReturnDocBlock(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             *
             * @return string
             */
            public function getFoo(): array
            {
                return [];
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('conflict');
        $this->parser->parse($classMetadata);
    }

    public function testVirtualPropertyWithInvalidReturnDocBlock(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             *
             * @return resource
             */
            public function getFoo()
            {
                return fopen(__FILE__, 'rb');
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('resource');
        $this->parser->parse($classMetadata);
    }

    public function testVirtualPropertyType(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             * @JMS\Type("string")
             */
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

    public function testVirtualPropertyTypeConflictingWithTypeHint(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             * @JMS\Type("integer")
             */
            public function foo(): string
            {
                return 'bar';
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('conflict');
        $this->parser->parse($classMetadata);
    }

    public function testVirtualPropertyTypeConflictingWithDocBlockType(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             * @JMS\Type("integer")
             *
             * @return string[]
             */
            public function foo(): array
            {
                return [];
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('conflict');
        $this->parser->parse($classMetadata);
    }

    public function testVirtualPropertyTypeExtendingPrimitive(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             * @JMS\Type("string")
             */
            public function foo(): ?string
            {
                return 'foo';
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('foo', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertPropertyVariation('foo', true, true, $property);
        $this->assertPropertyType(PropertyTypePrimitive::class, 'string|null', true, $property->getType());
    }

    public function testVirtualPropertyTypeExtendingDateTime(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             * @JMS\Type("DateTime<'Y-m-d H:i:s', 'Europe/Zurich', 'Y-m-d'>")
             */
            public function foo(): ?\DateTime
            {
                return new \DateTime();
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('foo', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertPropertyVariation('foo', true, true, $property);
        /** @var PropertyTypeDateTime $type */
        $type = $property->getType();
        $this->assertPropertyType(PropertyTypeDateTime::class, 'DateTime|null', true, $type);
        $this->assertSame('Y-m-d H:i:s', $type->getFormat(), 'Date time format should match');
        $this->assertSame('Europe/Zurich', $type->getZone(), 'Date time zone should match');
        $this->assertSame('Y-m-d', $type->getDeserializeFormat(), 'Date time deserialize format should match');
    }

    public function testVirtualPropertyTypeExtendingDateTimeWithUnknown(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             * @JMS\Type("DateTime<'Y-m-d H:i:s', 'Europe/Zurich', 'Y-m-d'>")
             */
            public function foo()
            {
                return 0;
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('foo', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertPropertyVariation('foo', true, true, $property);
        /** @var PropertyTypeDateTime $type */
        $type = $property->getType();
        $this->assertPropertyType(PropertyTypeDateTime::class, 'DateTime|null', true, $type);
        $this->assertSame('Y-m-d H:i:s', $type->getFormat(), 'Date time format should match');
        $this->assertSame('Europe/Zurich', $type->getZone(), 'Date time zone should match');
        $this->assertSame('Y-m-d', $type->getDeserializeFormat(), 'Date time deserialize format should match');
    }

    public function testVirtualPropertyInvalidType(): void
    {
        if (!class_exists(NamedArgumentConstructor::class)) {
            $this->markTestSkipped('Before doctrine/annotations 1.12, the exception message is different');
        }
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             * @JMS\Type("__invalid__")
             */
            public function foo()
            {
                return 'bar';
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('unexpected "__" (T_UNKNOWN)');
        $this->parser->parse($classMetadata);
    }

    public function testInvalidVirtualPropertyAnnotations(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             * @JMS\Type
             */
            public function getFoo(): int
            {
                return 0;
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Type');
        $this->parser->parse($classMetadata);
    }

    public function testPrivateVirtualProperty(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             */
            private function foo(): string
            {
                return 'bar';
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('public');
        $this->parser->parse($classMetadata);
    }

    public function testVirtualPropertyExclude(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             * @JMS\Exclude
             */
            public function getFoo()
            {
                return 0;
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(0, $props, 'Number of properties should match');
    }

    public function testVirtualPropertyWithGroups(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             * @JMS\Groups({"group1", "group2"})
             */
            public function getFoo()
            {
                return 0;
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('foo', 1, $props[0]);
        $this->assertSame(['group1', 'group2'], $props[0]->getVariations()[0]->getGroups());
    }

    public function testVirtualPropertyWithVersionRange(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             * @JMS\Since("1.2")
             * @JMS\Until("3.9")
             */
            public function getFoo()
            {
                return 0;
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('foo', 1, $props[0]);
        $property = $props[0]->getVariations()[0];
        $this->assertFalse($property->getVersionRange()->isIncluded('1.0'));
        $this->assertTrue($property->getVersionRange()->isIncluded('1.2'));
        $this->assertFalse($property->getVersionRange()->isIncluded('4.0'));
    }

    public function testVirtualPropertyOverridesProperty(): void
    {
        $c = new class() {
            /**
             * @JMS\Type("string")
             */
            private $foo;

            /**
             * @JMS\VirtualProperty
             */
            public function getFoo(): int
            {
                return 0;
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('foo', 2, $props[0]);

        $property = $props[0]->getVariations()[0];
        $this->assertSame('foo', $property->getName());
        $this->assertPropertyType(PropertyTypePrimitive::class, 'string|null', true, $property->getType());

        $property = $props[0]->getVariations()[1];
        $this->assertSame('foo', $property->getName());
        $this->assertPropertyType(PropertyTypePrimitive::class, 'int', false, $property->getType());
    }

    public function testVirtualPropertyWithSerializedNameOverridesProperty(): void
    {
        $c = new class() {
            /**
             * @JMS\Type("string")
             */
            private $foo;

            /**
             * @JMS\VirtualProperty
             * @JMS\SerializedName("foo")
             */
            public function getBar(): int
            {
                return 0;
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('foo', 2, $props[0]);

        $property = $props[0]->getVariations()[0];
        $this->assertSame('foo', $property->getName());
        $this->assertPropertyType(PropertyTypePrimitive::class, 'string|null', true, $property->getType());

        $property = $props[0]->getVariations()[1];
        $this->assertSame('bar', $property->getName());
        $this->assertPropertyType(PropertyTypePrimitive::class, 'int', false, $property->getType());
    }

    public function testVirtualPropertyXmlAnnotations(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             * @JMS\XmlAttribute
             * @JMS\XmlKeyValuePairs
             * @JMS\XmlList
             * @JMS\XmlMap
             * @JMS\XmlValue
             */
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
    }

    public function testUnsupportedVirtualPropertyAnnotations(): void
    {
        $c = new class() {
            /**
             * @JMS\VirtualProperty
             * @JMS\PreSerialize
             */
            public function foo(): string
            {
                return 'bar';
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('unsupported annotation');
        $this->parser->parse($classMetadata);
    }

    public function testPostDeserializedMethods(): void
    {
        $c = new class() {
            /**
             * @JMS\PostDeserialize
             */
            public function foo(): void
            {
            }

            /**
             * @JMS\PostDeserialize
             */
            public function bar(): void
            {
            }
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $this->assertSame(['foo', 'bar'], $classMetadata->getPostDeserializeMethods());
    }

    protected function assertPropertyCollection(string $serializedName, int $variations, PropertyCollection $prop): void
    {
        $this->assertSame($serializedName, $prop->getSerializedName(), 'Serialized name of property should match');
        $this->assertCount($variations, $prop->getVariations(), "Number of variations of property {$serializedName} should match");
    }

    protected function assertPropertyVariation(string $name, bool $public, bool $readOnly, PropertyVariationMetadata $property): void
    {
        $this->assertSame($name, $property->getName(), 'Name of property should match');
        $this->assertSame($public, $property->isPublic(), "Public flag of property {$name} should match");
        $this->assertSame($readOnly, $property->isReadOnly(), "Read only flag of property {$name} should match");
    }

    private function assertPropertyType(string $propertyTypeClass, string $typeString, bool $nullable, PropertyType $type): void
    {
        $this->assertInstanceOf($propertyTypeClass, $type);
        $this->assertSame($nullable, $type->isNullable(), 'Nullable flag should match');
        $this->assertSame($typeString, (string) $type);
    }

    private function assertPropertyAccessor(?string $getterMethod, ?string $setterMethod, PropertyAccessor $accessor): void
    {
        $this->assertSame($getterMethod, $accessor->getGetterMethod(), 'Getter method of property should match');
        $this->assertSame($setterMethod, $accessor->getSetterMethod(), 'Setter method of property should match');
    }
}

if (PHP_VERSION_ID > 80100) {
    require 'JMSParserTest81.php';
} else {
    require 'JMSParserTestLegacy.php';
}
