<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\Metadata;

use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeArray;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\Metadata\PropertyTypeDateTime;
use Liip\MetadataParser\Metadata\PropertyTypePrimitive;
use Liip\MetadataParser\Metadata\PropertyTypeUnknown;
use PHPUnit\Framework\TestCase;

/**
 * @small
 */
class PropertyTypeTest extends TestCase
{
    public function provideTypesToMerge(): iterable
    {
        yield [
            new PropertyTypeUnknown(true),
            new PropertyTypeUnknown(false),
            'mixed',
            false,
        ];

        yield [
            new PropertyTypePrimitive('string', true),
            new PropertyTypeUnknown(false),
            'string',
            false,
        ];

        yield [
            new PropertyTypeDateTime(false, true),
            new PropertyTypeUnknown(false),
            'DateTime',
            false,
        ];

        yield [
            new PropertyTypeClass(\stdClass::class, true),
            new PropertyTypeUnknown(false),
            'stdClass',
            false,
        ];

        yield [
            new PropertyTypeArray(new PropertyTypePrimitive('bool', false), false, true),
            new PropertyTypeUnknown(false),
            'bool[]',
            false,
        ];

        yield [
            new PropertyTypePrimitive('string', true),
            new PropertyTypePrimitive('string', false),
            'string',
            false,
        ];

        yield [
            new PropertyTypeDateTime(false, true),
            new PropertyTypeDateTime(false, false),
            'DateTime',
            false,
        ];

        yield [
            new PropertyTypeClass(\stdClass::class, true),
            new PropertyTypeClass(\stdClass::class, false),
            'stdClass',
            false,
        ];

        yield [
            new PropertyTypeArray(new PropertyTypePrimitive('bool', false), false, true),
            new PropertyTypeArray(new PropertyTypePrimitive('bool', false), false, false),
            'bool[]',
            false,
        ];

        yield [
            new PropertyTypeArray(new PropertyTypePrimitive('bool', false), false, true),
            new PropertyTypeArray(new PropertyTypeUnknown(false), false, false),
            'bool[]',
            false,
        ];

        yield [
            new PropertyTypeArray(new PropertyTypeUnknown(false), false, false),
            new PropertyTypeArray(new PropertyTypePrimitive('bool', false), false, true),
            'bool[]',
            false,
        ];
    }

    /**
     * @dataProvider provideTypesToMerge
     */
    public function testMerge(PropertyType $typeA, PropertyType $typeB, string $expectedType, bool $expectedNullable): void
    {
        $result = $typeA->merge($typeB);

        $this->assertSame($expectedType, (string) $result);
        $this->assertSame($expectedNullable, $result->isNullable(), 'Nullable flag should match');
    }

    /**
     * Special case: array to hashmap is allowed. hashmap to array is not.
     */
    public function testUpgradeToHashmap(): void
    {
        $array = new PropertyTypeArray(new PropertyTypePrimitive('bool', false), false, true);
        $hashmap = new PropertyTypeArray(new PropertyTypePrimitive('bool', false), true, true);

        /** @var PropertyTypeArray $merged */
        $merged = $array->merge($hashmap);
        $this->assertInstanceOf(PropertyTypeArray::class, $merged);
        $this->assertTrue($merged->isNullable());
        $this->assertTrue($merged->isHashmap());
        /** @var PropertyTypePrimitive $inner */
        $inner = $merged->getSubType();
        $this->assertInstanceOf(PropertyTypePrimitive::class, $inner);
        $this->assertSame('bool', $inner->getTypeName());

        $this->expectException(\UnexpectedValueException::class);
        $hashmap->merge($array);
    }

    public function testMergeInvalidTypes(): void
    {
        $types = $this->getDifferentTypes();

        foreach ($types as $typeA) {
            foreach ($types as $typeB) {
                if ($typeA === $typeB || $typeB instanceof PropertyTypeUnknown) {
                    continue;
                }

                try {
                    $typeA->merge($typeB);
                    $this->fail(sprintf('Merge of %s into %s should not be possible', (string) $typeB, (string) $typeA));
                } catch (\UnexpectedValueException $e) {
                    $this->assertStringContainsString('merge', $e->getMessage());
                }
            }
        }
    }

    /**
     * @return PropertyType[]
     */
    private function getDifferentTypes(): array
    {
        return [
            new PropertyTypeUnknown(true),
            new PropertyTypePrimitive('string', true),
            new PropertyTypePrimitive('int', true),
            new PropertyTypeDateTime(false, true),
            new PropertyTypeDateTime(true, true),
            new PropertyTypeClass(\stdClass::class, true),
            new PropertyTypeArray(new PropertyTypePrimitive('bool', false), false, true),
            new PropertyTypeArray(new PropertyTypePrimitive('int', false), false, true),
            new PropertyTypeArray(new PropertyTypePrimitive('string', false), true, true),
        ];
    }
}
