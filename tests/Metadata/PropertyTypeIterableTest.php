<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\Metadata;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\Metadata\PropertyTypeIterable;
use Liip\MetadataParser\Metadata\PropertyTypePrimitive;
use PHPUnit\Framework\TestCase;

/**
 * @small
 */
class PropertyTypeIterableTest extends TestCase
{
    public function testNestedArrayLeaf(): void
    {
        $propertyType = new PropertyTypeIterable(new PropertyTypeIterable(new PropertyTypePrimitive('int', false), false, false), false, false);

        $this->assertInstanceOf(PropertyTypeIterable::class, $propertyType->getSubType());
        $this->assertInstanceOf(PropertyTypePrimitive::class, $propertyType->getSubType()->getSubType());
        $this->assertInstanceOf(PropertyTypePrimitive::class, $propertyType->getLeafType());
    }

    public function testDefaultListIsNotCollection(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $list = new PropertyTypeIterable($subType, false, false);

        $this->assertFalse($list->isTraversable());
    }

    public function testConcreteCollectionClassIsKept(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $collection = new PropertyTypeIterable($subType, false, false, ArrayCollection::class);

        $this->assertTrue($collection->isTraversable());
        $this->assertSame(ArrayCollection::class, $collection->getTraversableClass());
    }

    public function testMergeListWithClassCollection(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $list = new PropertyTypeIterable($subType, false, false);

        $this->expectException(\UnexpectedValueException::class);
        $list->merge(new PropertyTypeClass(Collection::class, true));
    }

    public function testMergeInterfaceCollectionListWithClassCollection(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $typeHintedCollection = new PropertyTypeClass(Collection::class, true);
        $defaultCollection = new PropertyTypeIterable($subType, false, false, Collection::class);

        $result = $defaultCollection->merge($typeHintedCollection);
        $this->assertInstanceOf(PropertyTypeIterable::class, $result);
        /* @var PropertyTypeIterable $result */
        $this->assertTrue($result->isTraversable());
        $this->assertSame((string) $defaultCollection->getSubType(), (string) $result->getSubType());
        $this->assertSame(Collection::class, $result->getTraversableClass());
    }

    public function testMergeClassInterfaceCollectionWithConcreteCollectionList(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $typeHintedCollection = new PropertyTypeClass(Collection::class, true);
        $explicitCollection = new PropertyTypeIterable($subType, false, false, ArrayCollection::class);

        $result = $typeHintedCollection->merge($explicitCollection);
        $this->assertInstanceOf(PropertyTypeIterable::class, $result);
        /* @var PropertyTypeIterable $result */
        $this->assertTrue($result->isTraversable());
        $this->assertSame((string) $explicitCollection->getSubType(), (string) $result->getSubType());
        $this->assertSame(ArrayCollection::class, $result->getTraversableClass());
    }

    public function testMergeConcreteCollectionListWithClassInterfaceCollection(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $typeHintedCollection = new PropertyTypeClass(Collection::class, true);
        $explicitCollection = new PropertyTypeIterable($subType, false, false, ArrayCollection::class);

        $result = $explicitCollection->merge($typeHintedCollection);
        $this->assertInstanceOf(PropertyTypeIterable::class, $result);
        /* @var PropertyTypeIterable $result */
        $this->assertTrue($result->isTraversable());
        $this->assertSame((string) $explicitCollection->getSubType(), (string) $result->getSubType());
        $this->assertSame(ArrayCollection::class, $result->getTraversableClass());
    }

    public function testMergeConcreteCollectionListWithInterfaceCollectionList(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $defaultCollection = new PropertyTypeIterable($subType, false, false, Collection::class);
        $explicitCollection = new PropertyTypeIterable($subType, false, false, ArrayCollection::class);

        $result = $explicitCollection->merge($defaultCollection);
        $this->assertInstanceOf(PropertyTypeIterable::class, $result);
        /* @var PropertyTypeIterable $result */
        $this->assertTrue($result->isTraversable());
        $this->assertSame((string) $explicitCollection->getSubType(), (string) $result->getSubType());
        $this->assertSame(ArrayCollection::class, $result->getTraversableClass());
    }

    public function testMergeDefaultCollectionListWithConcreteCollectionList(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $defaultCollection = new PropertyTypeIterable($subType, false, false, Collection::class);
        $explicitCollection = new PropertyTypeIterable($subType, false, false, ArrayCollection::class);

        $result = $defaultCollection->merge($explicitCollection);
        $this->assertInstanceOf(PropertyTypeIterable::class, $result);
        /* @var PropertyTypeIterable $result */
        $this->assertTrue($result->isTraversable());
        $this->assertSame((string) $explicitCollection->getSubType(), (string) $result->getSubType());
        $this->assertSame(ArrayCollection::class, $result->getTraversableClass());
    }

    public function testMergeListAndInterfaceCollectionList(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $list = new PropertyTypeIterable($subType, false, false);
        $collection = new PropertyTypeIterable($subType, false, false, Collection::class);

        foreach ([$list->merge($collection), $collection->merge($list)] as $result) {
            $this->assertInstanceOf(PropertyTypeIterable::class, $result);
            /* @var PropertyTypeIterable $result */
            $this->assertTrue($result->isTraversable());
            $this->assertSame(Collection::class, $result->getTraversableClass());
            $this->assertSame((string) $list->getSubType(), (string) $result->getSubType());
        }
    }

    public function testMergeListWithConcreteCollectionList(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $list = new PropertyTypeIterable($subType, false, false);
        $collection = new PropertyTypeIterable($subType, false, false, ArrayCollection::class);

        foreach ([$list->merge($collection), $collection->merge($list)] as $result) {
            $this->assertInstanceOf(PropertyTypeIterable::class, $result);
            /* @var PropertyTypeIterable $result */
            $this->assertTrue($result->isTraversable());
            $this->assertSame(ArrayCollection::class, $result->getTraversableClass());
            $this->assertSame((string) $list->getSubType(), (string) $result->getSubType());
        }
    }
}
