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

        $this->assertCount(1, $collection->getVariations());
        $this->assertSame('test', $collection->getSerializedName());

        $rawClassMetadata->renameProperty('test', 'new_name');
        $this->assertFalse($rawClassMetadata->hasPropertyCollection('test'));
        $this->assertTrue($rawClassMetadata->hasPropertyCollection('new_name'));
        $collection = $rawClassMetadata->getPropertyCollection('new_name');
        $this->assertCount(1, $collection->getVariations());
        $this->assertSame('new_name', $collection->getSerializedName());

        $this->assertTrue($collection->hasVariation('testProperty'));
        $variation = $collection->getVariation('testProperty');
        $this->assertSame('testProperty', $variation->getName());
    }

    public function testRenameMerge(): void
    {
        $rawClassMetadata = new RawClassMetadata('Foo');
        $rawClassMetadata->addPropertyVariation('testProperty', new PropertyVariationMetadata('testProperty', true, false));
        $rawClassMetadata->addPropertyVariation('test', new PropertyVariationMetadata('test', true, false));

        $this->assertTrue($rawClassMetadata->hasPropertyCollection('testProperty'));
        $this->assertTrue($rawClassMetadata->hasPropertyCollection('test'));

        $rawClassMetadata->renameProperty('testProperty', 'test');
        $this->assertFalse($rawClassMetadata->hasPropertyCollection('testProperty'));
        $this->assertTrue($rawClassMetadata->hasPropertyCollection('test'));
        $collection = $rawClassMetadata->getPropertyCollection('test');
        $this->assertCount(2, $collection->getVariations());
        $this->assertSame('test', $collection->getSerializedName());

        $this->assertTrue($collection->hasVariation('testProperty'));
        $variation = $collection->getVariation('testProperty');
        $this->assertSame('testProperty', $variation->getName());
        $this->assertTrue($collection->hasVariation('test'));
        $variation = $collection->getVariation('test');
        $this->assertSame('test', $variation->getName());
    }

    public function testRemove(): void
    {
        $rawClassMetadata = new RawClassMetadata('Foo');
        $rawClassMetadata->addPropertyVariation('test', new PropertyVariationMetadata('testProperty', true, false));

        $this->assertCount(1, $rawClassMetadata->getPropertyCollections());
        $this->assertTrue($rawClassMetadata->hasPropertyVariation('testProperty'));
        $this->assertTrue($rawClassMetadata->hasPropertyCollection('test'));

        $rawClassMetadata->removePropertyVariation('testProperty');

        $this->assertCount(0, $rawClassMetadata->getPropertyCollections());
        $this->assertFalse($rawClassMetadata->hasPropertyVariation('testProperty'));
        $this->assertFalse($rawClassMetadata->hasPropertyCollection('test'));
    }
}
