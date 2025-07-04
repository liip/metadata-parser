<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser;

use Doctrine\Common\Annotations\AnnotationReader;
use JMS\Serializer\Annotation\SerializedName;
use Liip\MetadataParser\Builder;
use Liip\MetadataParser\Metadata\PropertyMetadata;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\Metadata\PropertyTypePrimitive;
use Liip\MetadataParser\ModelParser\JMSParser;
use Liip\MetadataParser\ModelParser\PhpDocParser;
use Liip\MetadataParser\ModelParser\ReflectionParser;
use Liip\MetadataParser\Parser;
use Liip\MetadataParser\RecursionChecker;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\Liip\MetadataParser\ModelParser\Model\Car;
use Tests\Liip\MetadataParser\ModelParser\Model\Moped;
use Tests\Liip\MetadataParser\ModelParser\Model\Nested;

/**
 * @small
 */
class BuilderTest extends TestCase
{
    private Builder $builder;

    protected function setUp(): void
    {
        $parser = new Parser(
            [
                new ReflectionParser(),
                new PhpDocParser(),
                new JMSParser(new AnnotationReader()),
            ]
        );

        $this->builder = new Builder(
            $parser,
            new RecursionChecker($this->createMock(LoggerInterface::class))
        );
    }

    public function testBuild(): void
    {
        $c = new class {
            /**
             * @var Nested
             */
            private $property;
        };

        $classMetadata = $this->builder->build(\get_class($c));

        $props = $classMetadata->getProperties();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertProperty('property', 'property', false, false, $props[0]);
        $this->assertPropertyType($props[0]->getType(), PropertyTypeClass::class, Nested::class, false);

        $type = $props[0]->getType();
        $this->assertInstanceOf(PropertyTypeClass::class, $type);
        $nestedMetadata = $type->getClassMetadata();
        $props = $nestedMetadata->getProperties();
        $this->assertCount(1, $props, 'Number of properties should match');
        $this->assertProperty('nestedProperty', 'nested_property', false, false, $props[0]);
    }

    public function testPropertyWithDifferentSerializedName(): void
    {
        $c = new class {
            /**
             * @SerializedName("myProperty")
             */
            #[SerializedName('myProperty')]
            public string $myProperty;
        };

        $classMetadata = $this->builder->build(\get_class($c));

        $props = $classMetadata->getProperties();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertProperty('myProperty', 'myProperty', true, false, $props[0]);
    }

    public function testDiscriminatorClassMetadataList(): void
    {
        $classMetadata = $this->builder->build(Car::class);

        $props = $classMetadata->getProperties();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertProperty('carProperty', 'car_property', true, false, $props[0]);
        $this->assertPropertyType($props[0]->getType(), PropertyTypePrimitive::class, 'string', false);

        $discriminatorMetadata = $classMetadata->getDiscriminatorMetadata();

        $this->assertNotNull($discriminatorMetadata);
        $this->assertCount(2, $discriminatorMetadata->getClassMetadataList());
        $this->assertNotNull($discriminatorMetadata->getMetadataForClass(Car::class));
        $this->assertNotNull($discriminatorMetadata->getMetadataForClass(Moped::class));
    }

    private function assertProperty(string $name, string $serializedName, bool $public, bool $readOnly, PropertyMetadata $property): void
    {
        $this->assertSame($name, $property->getName(), 'Name of property should match');
        $this->assertSame($serializedName, $property->getSerializedName(), "Serialized name of property {$name} should match");
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
