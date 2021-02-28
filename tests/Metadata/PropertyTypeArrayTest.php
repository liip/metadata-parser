<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\Metadata;

use Liip\MetadataParser\Metadata\PropertyTypeArray;
use Liip\MetadataParser\Metadata\PropertyTypePrimitive;
use PHPUnit\Framework\TestCase;

/**
 * @small
 */
class PropertyTypeArrayTest extends TestCase
{
    public function testNestedArrayLeaf(): void
    {
        $propertyType = new PropertyTypeArray(new PropertyTypeArray(new PropertyTypePrimitive('int', false), false, false), false, false);

        static::assertInstanceOf(PropertyTypeArray::class, $propertyType->getSubType());
        static::assertInstanceOf(PropertyTypePrimitive::class, $propertyType->getSubType()->getSubType());
        static::assertInstanceOf(PropertyTypePrimitive::class, $propertyType->getLeafType());
    }
}
