<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\TypeParser;

use Liip\MetadataParser\Exception\InvalidTypeException;
use Liip\MetadataParser\TypeParser\PhpTypeParser;
use PHPUnit\Framework\TestCase;
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

    public function provideTypes(): iterable
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
            '\stdClass[][string]',
            'stdClass[][string]',
        ];
    }

    /**
     * @dataProvider provideTypes
     */
    public function testPropertyType(string $rawType, string $expectedType, bool $expectedNullable = null): void
    {
        $type = $this->parser->parseAnnotationType($rawType, new \ReflectionClass($this));

        $this->assertSame($expectedType, (string) $type, 'Type should match');
        if (null !== $expectedNullable) {
            $this->assertSame($expectedNullable, $type->isNullable(), 'Nullable flag should match');
        }
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

    public function provideLocalClassTypes()
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
    }

    /**
     * @dataProvider provideLocalClassTypes
     */
    public function testNamespaceResolution(string $rawType, string $expectedType): void
    {
        $type = $this->parser->parseAnnotationType($rawType, new \ReflectionClass(WithImports::class));

        $this->assertSame($expectedType, (string) $type, 'Type should match');
    }

    public function provideReflectionTypes(): iterable
    {
        $c = new class() {
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
     * @dataProvider provideReflectionTypes
     */
    public function testReflectionType(\ReflectionType $reflType, string $expectedType, bool $expectedNullable = null): void
    {
        $type = $this->parser->parseReflectionType($reflType);

        $this->assertSame($expectedType, (string) $type, 'Type should match');
        if (null !== $expectedNullable) {
            $this->assertSame($expectedNullable, $type->isNullable(), 'Nullable flag should match');
        }
    }
}
