<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\TypeParser;

use Liip\MetadataParser\Exception\InvalidTypeException;
use Liip\MetadataParser\Metadata\PropertyTypeDateTime;
use Liip\MetadataParser\Metadata\PropertyTypeEnum;
use Liip\MetadataParser\Metadata\PropertyTypeIterable;
use Liip\MetadataParser\TypeParser\JMSTypeParser;
use PHPUnit\Framework\TestCase;
use Tests\Liip\MetadataParser\ModelParser\Fixtures\DirectionEnum;
use Tests\Liip\MetadataParser\ModelParser\Fixtures\SuitEnum;

/**
 * @small
 */
class JMSTypeParserTest extends TestCase
{
    /**
     * @var JMSTypeParser
     */
    private $parser;

    protected function setUp(): void
    {
        $this->parser = new JMSTypeParser();
    }

    public static function provideTypeCases(): iterable
    {
        yield [
            '',
            'mixed',
            true,
        ];

        yield [
            'array',
            'array',
            true,
        ];

        yield [
            'string',
            'string|null',
        ];

        yield [
            'boolean',
            'bool|null',
        ];

        yield [
            'integer',
            'int|null',
        ];

        yield [
            'double',
            'float|null',
        ];

        yield [
            'stdClass',
            'stdClass|null',
        ];

        yield [
            'array<string>',
            'string[]|null',
        ];

        yield [
            'array<array<array<boolean>>>',
            'bool[][][]|null',
        ];

        yield [
            'array<int>',
            'int[]|null',
        ];

        yield [
            'array<string, int>',
            'int[string]|null',
        ];

        yield [
            'array<string, array<string, array<string, bool>>>',
            'bool[string][string][string]|null',
        ];

        yield [
            'array<string, array<array<string, bool>>>',
            'bool[string][][string]|null',
        ];
    }

    /**
     * @dataProvider provideTypeCases
     */
    public function testType(string $rawType, string $expectedType, ?bool $expectedNullable = null): void
    {
        $type = $this->parser->parse($rawType);

        $this->assertSame($expectedType, (string) $type, 'Type should match');
        if (null !== $expectedNullable) {
            $this->assertSame($expectedNullable, $type->isNullable(), 'Nullable flag should match');
        }
    }

    public static function provideDateTimeTypeCases(): iterable
    {
        yield [
            'DateTime',
            'DateTime|null',
            null,
            null,
            null,
        ];

        yield [
            'DateTime<\'Y-m-d H:i:s\'>',
            'DateTime|null',
            'Y-m-d H:i:s',
            null,
            ['Y-m-d H:i:s'],
        ];

        yield [
            'DateTime<\'\', \'Europe/Zurich\'>',
            'DateTime|null',
            null,
            'Europe/Zurich',
            null,
        ];

        yield [
            'DateTime<\'\', \'\', \'Y-m-d\'>',
            'DateTime|null',
            null,
            null,
            ['Y-m-d'],
        ];

        yield [
            'DateTime<\'Y-m-d H:i:s\', \'Europe/Zurich\', \'Y-m-d\'>',
            'DateTime|null',
            'Y-m-d H:i:s',
            'Europe/Zurich',
            ['Y-m-d'],
        ];

        yield [
            'DateTimeImmutable',
            'DateTimeImmutable|null',
            null,
            null,
            null,
        ];

        yield [
            'DateTimeImmutable<\'Y-m-d H:i:s\'>',
            'DateTimeImmutable|null',
            'Y-m-d H:i:s',
            null,
            ['Y-m-d H:i:s'],
        ];

        yield [
            'DateTimeImmutable<\'\', \'Europe/Zurich\'>',
            'DateTimeImmutable|null',
            null,
            'Europe/Zurich',
            null,
        ];

        yield [
            'DateTimeImmutable<\'\', \'\', \'Y-m-d\'>',
            'DateTimeImmutable|null',
            null,
            null,
            ['Y-m-d'],
        ];

        yield [
            'DateTimeImmutable<\'Y-m-d H:i:s\', \'Europe/Zurich\', \'Y-m-d\'>',
            'DateTimeImmutable|null',
            'Y-m-d H:i:s',
            'Europe/Zurich',
            ['Y-m-d'],
        ];
    }

    /**
     * @dataProvider provideDateTimeTypeCases
     */
    public function testDateTimeType(string $rawType, string $expectedType, ?string $expectedFormat, ?string $expectedZone, ?array $expectedDeserializeFormats): void
    {
        /** @var PropertyTypeDateTime $type */
        $type = $this->parser->parse($rawType);
        $this->assertInstanceOf(PropertyTypeDateTime::class, $type);
        $this->assertSame($expectedType, (string) $type, 'Type should match');
        $this->assertSame($expectedFormat, $type->getFormat(), 'Date time format should match');
        $this->assertSame($expectedZone, $type->getZone(), 'Date time zone should match');
        $this->assertSame($expectedDeserializeFormats, $type->getDeserializeFormats(), 'Date time deserialize format should match');
    }

    public function testInvalidTypeWithParameters(): void
    {
        $this->expectException(InvalidTypeException::class);
        $this->parser->parse('stdClass<string>');
    }

    public function testArrayWithTooManyParameters(): void
    {
        $this->expectException(InvalidTypeException::class);
        $this->parser->parse('array<string, int, bool>');
    }

    public function testEnumBackedParsesAsPropertyTypeEnum(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Enum types are only supported in PHP 8.1 or newer');
        }

        /** @var PropertyTypeEnum $type */
        $type = $this->parser->parse('enum<'.SuitEnum::class.'>');

        $this->assertInstanceOf(PropertyTypeEnum::class, $type);
        $this->assertSame(SuitEnum::class, $type->getClassName());
        $this->assertTrue($type->isNullable());
        $this->assertTrue($type->isBackedEnum());
        $this->assertNull($type->getSerializationMode());
        $this->assertTrue($type->shouldSerializeAsValue());
    }

    public function testEnumUnitParsesAsPropertyTypeEnum(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Enum types are only supported in PHP 8.1 or newer');
        }

        /** @var PropertyTypeEnum $type */
        $type = $this->parser->parse('enum<'.DirectionEnum::class.'>');
        $this->assertInstanceOf(PropertyTypeEnum::class, $type);
        $this->assertSame(DirectionEnum::class, $type->getClassName());
        $this->assertFalse($type->isBackedEnum());
        $this->assertNull($type->getSerializationMode());
        $this->assertFalse($type->shouldSerializeAsValue());
    }

    public function testEnumWithNameModeDisablesValueSerialization(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Enum types are only supported in PHP 8.1 or newer');
        }

        /** @var PropertyTypeEnum $type */
        $type = $this->parser->parse('enum<'.SuitEnum::class.", 'name'>");
        $this->assertInstanceOf(PropertyTypeEnum::class, $type);
        $this->assertTrue($type->isBackedEnum());
        $this->assertSame('name', $type->getSerializationMode());
        $this->assertFalse($type->shouldSerializeAsValue());
    }

    public function testEnumWithValueModeKeepsValueSerialization(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Enum types are only supported in PHP 8.1 or newer');
        }

        /** @var PropertyTypeEnum $type */
        $type = $this->parser->parse('enum<'.SuitEnum::class.", 'value'>");
        $this->assertInstanceOf(PropertyTypeEnum::class, $type);
        $this->assertTrue($type->isBackedEnum());
        $this->assertSame('value', $type->getSerializationMode());
        $this->assertTrue($type->shouldSerializeAsValue());
    }

    public function testArrayOfEnumParsesCorrectly(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Enum types are only supported in PHP 8.1 or newer');
        }

        /** @var PropertyTypeIterable $type */
        $type = $this->parser->parse('array<enum<'.SuitEnum::class.'>>');
        $this->assertInstanceOf(PropertyTypeIterable::class, $type);
        $subType = $type->getLeafType();
        $this->assertInstanceOf(PropertyTypeEnum::class, $subType);
        $this->assertSame(SuitEnum::class, $subType->getClassName());
        $this->assertFalse($subType->isNullable()); // sub-types in JMS arrays are non-nullable
    }

    public function testEnumWithoutClassParamThrows(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Enum types are only supported in PHP 8.1 or newer');
        }

        $this->expectException(InvalidTypeException::class);
        $this->parser->parse('enum');
    }

    public function testEnumWithValueSerializationModeButNoSupportedBackedEnumThrowsException(): void
    {
        if (\PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Enum types are only supported in PHP 8.1 or newer');
        }

        $this->expectException(InvalidTypeException::class);
        $this->parser->parse('enum<'.DirectionEnum::class.', \'value\'>');
    }
}
