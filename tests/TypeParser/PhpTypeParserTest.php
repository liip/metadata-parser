<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\TypeParser;

use Liip\MetadataParser\Exception\InvalidTypeException;
use Liip\MetadataParser\Metadata\PropertyTypeEnum;
use Liip\MetadataParser\Metadata\PropertyTypeIterable;
use Liip\MetadataParser\TypeParser\PhpTypeParser;
use PHPUnit\Framework\TestCase;
use Tests\Liip\MetadataParser\ModelParser\Fixtures\DirectionEnum;
use Tests\Liip\MetadataParser\ModelParser\Fixtures\EnumModel;
use Tests\Liip\MetadataParser\ModelParser\Fixtures\SuitEnum;
use Tests\Liip\MetadataParser\ModelParser\Model\BaseModel;
use Tests\Liip\MetadataParser\ModelParser\Model\ReflectionAbstractModel;
use Tests\Liip\MetadataParser\ModelParser\Model\WithImports;
use Tests\Liip\MetadataParser\RecursionContextTest;

/**
 * @small
 */
class PhpTypeParserTest extends TestCase
{
    /**
     * @var PhpTypeParser
     */
    private $parser;

    protected function setUp(): void
    {
        $this->parser = new PhpTypeParser();
    }

    public static function providePropertyTypeCases(): iterable
    {
        yield [
            '',
            'mixed',
            true,
        ];

        yield [
            'mixed',
            'mixed',
            true,
        ];

        yield [
            'object',
            'mixed',
            false,
        ];

        yield [
            'array',
            'array',
            false,
        ];

        yield [
            'string',
            'string',
        ];

        yield [
            'boolean',
            'bool',
        ];

        yield [
            'integer',
            'int',
        ];

        yield [
            'double',
            'float',
        ];

        yield [
            'int|null',
            'int|null',
        ];

        yield [
            '\stdClass|null',
            'stdClass|null',
        ];

        yield [
            '\DateTime',
            'DateTime',
        ];

        yield [
            '\DateTimeImmutable',
            'DateTimeImmutable',
        ];

        yield [
            'string[]',
            'string[]',
        ];

        yield [
            'string[][][]',
            'string[][][]',
        ];

        yield [
            'string[string]',
            'string[string]',
        ];

        yield [
            'string[string][string][string]',
            'string[string][string][string]',
        ];

        yield [
            'string[][string][]',
            'string[][string][]',
        ];

        yield [
            '\stdClass[]|null',
            'stdClass[]|null',
        ];

        yield [
            'string[]|\Doctrine\Common\Collections\Collection|null',
            'string[]|\Doctrine\Common\Collections\Collection<string>|null',
        ];

        yield [
            '\stdClass[][string]',
            'stdClass[][string]',
        ];
    }

    public static function providePropertyTypeArrayIsCollectionCases(): iterable
    {
        yield [
            'string[]|\Doctrine\Common\Collections\Collection',
        ];

        yield [
            'string[]|\Doctrine\Common\Collections\ArrayCollection',
        ];
    }

    /**
     * @dataProvider providePropertyTypeCases
     */
    public function testPropertyType(string $rawType, string $expectedType, ?bool $expectedNullable = null): void
    {
        $type = $this->parser->parseAnnotationType($rawType, new \ReflectionClass($this));

        $this->assertSame($expectedType, (string) $type, 'Type should match');
        if (null !== $expectedNullable) {
            $this->assertSame($expectedNullable, $type->isNullable(), 'Nullable flag should match');
        }
    }

    /**
     * @dataProvider providePropertyTypeArrayIsCollectionCases
     */
    public function testPropertyTypeArrayIsCollection(string $rawType): void
    {
        $type = $this->parser->parseAnnotationType($rawType, new \ReflectionClass($this));
        self::assertInstanceOf(PropertyTypeIterable::class, $type);
        self::assertTrue($type->isTraversable());
    }

    public function testMultiType(): void
    {
        $this->expectException(InvalidTypeException::class);
        $this->parser->parseAnnotationType('string|int', new \ReflectionClass($this));
    }

    public function testResourceType(): void
    {
        $this->expectException(InvalidTypeException::class);
        $this->parser->parseAnnotationType('resource', new \ReflectionClass($this));
    }

    public static function provideNamespaceResolutionCases(): iterable
    {
        yield [
            'ReflectionAbstractModel',
            ReflectionAbstractModel::class,
        ];

        yield [
            'ReflectionBaseModel',
            RecursionContextTest::class,
        ];

        yield [
            'Nested',
            BaseModel::class,
        ];

        yield [
            'Nested[string][]',
            BaseModel::class.'[string][]',
        ];

        yield [
            'Nested[]|Collection',
            BaseModel::class.'[]|\Doctrine\Common\Collections\Collection<'.BaseModel::class.'>',
        ];
    }

    /**
     * @dataProvider provideNamespaceResolutionCases
     */
    public function testNamespaceResolution(string $rawType, string $expectedType): void
    {
        $type = $this->parser->parseAnnotationType($rawType, new \ReflectionClass(WithImports::class));

        $this->assertSame($expectedType, (string) $type, 'Type should match');
    }

    public static function provideReflectionTypeCases(): iterable
    {
        $c = new class {
            private function method1(): string
            {
                return '1';
            }

            private function method2(): ?int
            {
                return 1;
            }

            private function method3(): array
            {
                return [1];
            }
        };
        $reflClass = new \ReflectionClass(\get_class($c));

        yield [
            $reflClass->getMethod('method1')->getReturnType(),
            'string',
        ];

        yield [
            $reflClass->getMethod('method2')->getReturnType(),
            'int|null',
        ];

        yield [
            $reflClass->getMethod('method3')->getReturnType(),
            'array',
            false,
        ];
    }

    /**
     * @dataProvider provideReflectionTypeCases
     */
    public function testReflectionType(\ReflectionType $reflType, string $expectedType, ?bool $expectedNullable = null): void
    {
        $type = $this->parser->parseReflectionType($reflType);

        $this->assertSame($expectedType, (string) $type, 'Type should match');
        if (null !== $expectedNullable) {
            $this->assertSame($expectedNullable, $type->isNullable(), 'Nullable flag should match');
        }
    }

    public function testEnumReflectionType(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Enum support requires PHP 8.1 or newer');
        }

        $reflClass = new \ReflectionClass(EnumModel::class);

        $type = $this->parser->parseReflectionType($reflClass->getProperty('direction')->getType());

        $this->assertInstanceOf(PropertyTypeEnum::class, $type);
        $this->assertNull($type->getBackingType());
        $this->assertFalse($type->shouldSerializeAsValue());
        $this->assertNull($type->getSerializationMode());
        $this->assertTrue($type->isNullable());
    }

    public function testBackedEnumReflectionType(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Enum support requires PHP 8.1 or newer');
        }

        $reflClass = new \ReflectionClass(EnumModel::class);

        $type = $this->parser->parseReflectionType($reflClass->getProperty('suit')->getType());

        $this->assertInstanceOf(PropertyTypeEnum::class, $type);
        $this->assertSame('string', $type->getBackingType());
        $this->assertTrue($type->shouldSerializeAsValue());
        $this->assertNull($type->getSerializationMode());
        $this->assertFalse($type->isNullable());
    }

    public function testEnumAnnotationType(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Enum support requires PHP 8.1 or newer');
        }

        $type = $this->parser->parseAnnotationType('\\'.SuitEnum::class, new \ReflectionClass($this));

        $this->assertInstanceOf(PropertyTypeEnum::class, $type);
        $this->assertSame('string', $type->getBackingType());
        $this->assertTrue($type->shouldSerializeAsValue());
        $this->assertNull($type->getSerializationMode());
        $this->assertFalse($type->isNullable());
    }

    public function testNullableUnitEnumAnnotationType(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Enum support requires PHP 8.1 or newer');
        }

        $type = $this->parser->parseAnnotationType('\\'.DirectionEnum::class.'|null', new \ReflectionClass($this));

        $this->assertInstanceOf(PropertyTypeEnum::class, $type);
        $this->assertNull($type->getBackingType());
        $this->assertFalse($type->shouldSerializeAsValue());
        $this->assertNull($type->getSerializationMode());
        $this->assertTrue($type->isNullable());
    }
}
