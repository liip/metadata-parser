<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\Metadata;

use Liip\MetadataParser\Exception\InvalidTypeException;
use Liip\MetadataParser\Metadata\PropertyTypeEnum;
use Liip\MetadataParser\Metadata\PropertyTypeUnknown;
use PHPUnit\Framework\TestCase;
use Tests\Liip\MetadataParser\ModelParser\Fixtures\DirectionEnum;
use Tests\Liip\MetadataParser\ModelParser\Fixtures\SuitEnum;

/**
 * @small
 */
class PropertyTypeEnumTest extends TestCase
{
    public function testToStringBackedEnum(): void
    {
        $type = new PropertyTypeEnum(SuitEnum::class, false);
        $this->assertSame(SuitEnum::class, (string) $type);
    }

    public function testToStringNullable(): void
    {
        $type = new PropertyTypeEnum(SuitEnum::class, true);
        $this->assertSame(SuitEnum::class.'|null', (string) $type);
    }

    public function testSerializationModeForBackedEnum(): void
    {
        $type = new PropertyTypeEnum(SuitEnum::class, false);
        $this->assertTrue($type->shouldSerializeAsValue());
    }

    public function testGetBackingTypeForBackedEnum(): void
    {
        $type = new PropertyTypeEnum(SuitEnum::class, false);
        $this->assertSame('string', $type->getBackingType());
    }

    public function testGetBackingTypeForUnitEnum(): void
    {
        $type = new PropertyTypeEnum(DirectionEnum::class, false);
        $this->assertNull($type->getBackingType());
        $this->assertNull($type->getSerializationMode());
    }

    public function testIsBackedEnum(): void
    {
        $backedType = new PropertyTypeEnum(SuitEnum::class, false);
        $this->assertTrue($backedType->isBackedEnum());

        $unitType = new PropertyTypeEnum(DirectionEnum::class, false);
        $this->assertFalse($unitType->isBackedEnum());
    }

    public function testMergeWithSameEnum(): void
    {
        $typeA = new PropertyTypeEnum(SuitEnum::class, true);
        $typeB = new PropertyTypeEnum(SuitEnum::class, false);

        $result = $typeA->merge($typeB);

        $this->assertInstanceOf(PropertyTypeEnum::class, $result);
        $this->assertSame(SuitEnum::class, $result->getClassName());
        $this->assertFalse($result->isNullable());
    }

    public function testMergeWithDifferentEnumThrows(): void
    {
        $typeA = new PropertyTypeEnum(SuitEnum::class, false);
        $typeB = new PropertyTypeEnum(DirectionEnum::class, false);

        $this->expectException(\UnexpectedValueException::class);
        $typeA->merge($typeB);
    }

    public function testMergeWithUnknownSucceeds(): void
    {
        $type = new PropertyTypeEnum(SuitEnum::class, true);
        $unknown = new PropertyTypeUnknown(false);

        $result = $type->merge($unknown);

        $this->assertInstanceOf(PropertyTypeEnum::class, $result);
        $this->assertSame(SuitEnum::class, $result->getClassName());
        $this->assertFalse($result->isNullable());
    }

    public function testConstructorThrowsForNonEnum(): void
    {
        $this->expectException(InvalidTypeException::class);
        new PropertyTypeEnum(\stdClass::class, false);
    }
}
