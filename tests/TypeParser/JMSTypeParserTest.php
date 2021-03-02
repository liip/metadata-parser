<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\TypeParser;

use Liip\MetadataParser\Exception\InvalidTypeException;
use Liip\MetadataParser\Metadata\PropertyTypeDateTime;
use Liip\MetadataParser\TypeParser\JMSTypeParser;
use PHPUnit\Framework\TestCase;

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

    public function provideTypes(): iterable
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
     * @dataProvider provideTypes
     */
    public function testType(string $rawType, string $expectedType, bool $expectedNullable = null): void
    {
        $type = $this->parser->parse($rawType);

        $this->assertSame($expectedType, (string) $type, 'Type should match');
        if (null !== $expectedNullable) {
            $this->assertSame($expectedNullable, $type->isNullable(), 'Nullable flag should match');
        }
    }

    public function provideDateTimeTypes(): iterable
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
            null,
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
            'Y-m-d',
        ];

        yield [
            'DateTime<\'Y-m-d H:i:s\', \'Europe/Zurich\', \'Y-m-d\'>',
            'DateTime|null',
            'Y-m-d H:i:s',
            'Europe/Zurich',
            'Y-m-d',
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
            null,
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
            'Y-m-d',
        ];

        yield [
            'DateTimeImmutable<\'Y-m-d H:i:s\', \'Europe/Zurich\', \'Y-m-d\'>',
            'DateTimeImmutable|null',
            'Y-m-d H:i:s',
            'Europe/Zurich',
            'Y-m-d',
        ];
    }

    /**
     * @dataProvider provideDateTimeTypes
     */
    public function testDateTimeType(string $rawType, string $expectedType, ?string $expectedFormat, ?string $expectedZone, ?string $expectedDeserializeFormat): void
    {
        /** @var PropertyTypeDateTime $type */
        $type = $this->parser->parse($rawType);
        $this->assertInstanceOf(PropertyTypeDateTime::class, $type);
        $this->assertSame($expectedType, (string) $type, 'Type should match');
        $this->assertSame($expectedFormat, $type->getFormat(), 'Date time format should match');
        $this->assertSame($expectedZone, $type->getZone(), 'Date time zone should match');
        $this->assertSame($expectedDeserializeFormat, $type->getDeserializeFormat(), 'Date time deserialize format should match');
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
}
