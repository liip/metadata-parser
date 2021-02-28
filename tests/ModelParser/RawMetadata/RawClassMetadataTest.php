<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser\RawMetadata;

use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;
use PHPUnit\Framework\TestCase;

/**
 * @small
 */
class RawClassMetadataTest extends TestCase
{
    public function testRename(): void
    {
        $rawClassMetadata = new RawClassMetadata('Foo');
        $rawClassMetadata->addPropertyVariation('test', new PropertyVariationMetadata('testProperty', true, false));
        $collection = $rawClassMetadata->getPropertyCollection('test');

        static::assertCount(1, $collection->getVariations());
        static::assertSame('test', $collection->getSerializedName());

        $rawClassMetadata->renameProperty('test', 'new_name');
        static::assertFalse($rawClassMetadata->hasPropertyCollection('test'));
        static::assertTrue($rawClassMetadata->hasPropertyCollection('new_name'));
        $collection = $rawClassMetadata->getPropertyCollection('new_name');
        static::assertCount(1, $collection->getVariations());
        static::assertSame('new_name', $collection->getSerializedName());

        static::assertTrue($collection->hasVariation('testProperty'));
        $variation = $collection->getVariation('testProperty');
        static::assertSame('testProperty', $variation->getName());
    }

    public function testRenameMerge(): void
    {
        $rawClassMetadata = new RawClassMetadata('Foo');
        $rawClassMetadata->addPropertyVariation('testProperty', new PropertyVariationMetadata('testProperty', true, false));
        $rawClassMetadata->addPropertyVariation('test', new PropertyVariationMetadata('test', true, false));

        static::assertTrue($rawClassMetadata->hasPropertyCollection('testProperty'));
        static::assertTrue($rawClassMetadata->hasPropertyCollection('test'));

        $rawClassMetadata->renameProperty('testProperty', 'test');
        static::assertFalse($rawClassMetadata->hasPropertyCollection('testProperty'));
        static::assertTrue($rawClassMetadata->hasPropertyCollection('test'));
        $collection = $rawClassMetadata->getPropertyCollection('test');
        static::assertCount(2, $collection->getVariations());
        static::assertSame('test', $collection->getSerializedName());

        static::assertTrue($collection->hasVariation('testProperty'));
        $variation = $collection->getVariation('testProperty');
        static::assertSame('testProperty', $variation->getName());
        static::assertTrue($collection->hasVariation('test'));
        $variation = $collection->getVariation('test');
        static::assertSame('test', $variation->getName());
    }

    public function testRemove(): void
    {
        $rawClassMetadata = new RawClassMetadata('Foo');
        $rawClassMetadata->addPropertyVariation('test', new PropertyVariationMetadata('testProperty', true, false));

        static::assertCount(1, $rawClassMetadata->getPropertyCollections());
        static::assertTrue($rawClassMetadata->hasPropertyVariation('testProperty'));
        static::assertTrue($rawClassMetadata->hasPropertyCollection('test'));

        $rawClassMetadata->removePropertyVariation('testProperty');

        static::assertCount(0, $rawClassMetadata->getPropertyCollections());
        static::assertFalse($rawClassMetadata->hasPropertyVariation('testProperty'));
        static::assertFalse($rawClassMetadata->hasPropertyCollection('test'));
    }
}
