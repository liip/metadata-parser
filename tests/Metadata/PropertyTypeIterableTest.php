<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\Metadata;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Liip\MetadataParser\Metadata\PropertyTypeArray;
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

        $this->assertFalse($list->isCollection());
    }

    /**
     * @deprecated This only checks the behaviour of deprecated class {@see PropertyTypeArray}
     */
    public function testDefaultCollectionClassIsCollectionInterface(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $collection = new PropertyTypeArray($subType, false, false, true);

        $this->assertTrue($collection->isCollection());
        $this->assertSame(Collection::class, $collection->getCollectionClass());
    }

    public function testExplicitCollectionClassIsKept(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $collection = new PropertyTypeIterable($subType, false, false, ArrayCollection::class);

        $this->assertTrue($collection->isCollection());
        $this->assertNotSame(Collection::class, $collection->getCollectionClass());
        $this->assertSame(ArrayCollection::class, $collection->getCollectionClass());
    }

    public function testMergeListWithClassCollection(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $list = new PropertyTypeIterable($subType, false, false);

        $this->expectException(\UnexpectedValueException::class);
        $list->merge(new PropertyTypeClass(Collection::class, true));
    }

    public function testMergeDefaultCollectionListWithClassCollection(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $typeHintedCollection = new PropertyTypeClass(Collection::class, true);
        $defaultCollection = new PropertyTypeArray($subType, false, false, true);

        $result = $defaultCollection->merge($typeHintedCollection);
        $this->assertInstanceOf(PropertyTypeIterable::class, $result);
        /* @var PropertyTypeIterable $result */
        $this->assertTrue($result->isCollection());
        $this->assertSame((string) $defaultCollection->getSubType(), (string) $result->getSubType());
        $this->assertSame(Collection::class, $result->getCollectionClass());
    }

    public function testMergeExplicitCollectionListWithClassCollection(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $typeHintedCollection = new PropertyTypeClass(Collection::class, true);
        $explicitCollection = new PropertyTypeIterable($subType, false, false, ArrayCollection::class);

        $result = $explicitCollection->merge($typeHintedCollection);
        $this->assertInstanceOf(PropertyTypeIterable::class, $result);
        /* @var PropertyTypeIterable $result */
        $this->assertTrue($result->isCollection());
        $this->assertSame((string) $explicitCollection->getSubType(), (string) $result->getSubType());
        $this->assertSame(ArrayCollection::class, $result->getCollectionClass());
    }

    public function testMergeExplicitCollectionListWithDefaultCollection(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $defaultCollection = new PropertyTypeArray($subType, false, false, true);
        $explicitCollection = new PropertyTypeIterable($subType, false, false, ArrayCollection::class);

        $result = $explicitCollection->merge($defaultCollection);
        $this->assertInstanceOf(PropertyTypeIterable::class, $result);
        /* @var PropertyTypeIterable $result */
        $this->assertTrue($result->isCollection());
        $this->assertSame((string) $explicitCollection->getSubType(), (string) $result->getSubType());
        $this->assertSame(ArrayCollection::class, $result->getCollectionClass());
    }

    public function testMergeDefaultCollectionListWithExplicitCollection(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $defaultCollection = new PropertyTypeArray($subType, false, false, true);
        $explicitCollection = new PropertyTypeIterable($subType, false, false, ArrayCollection::class);

        $result = $defaultCollection->merge($explicitCollection);
        $this->assertInstanceOf(PropertyTypeIterable::class, $result);
        /* @var PropertyTypeIterable $result */
        $this->assertTrue($result->isCollection());
        $this->assertSame((string) $explicitCollection->getSubType(), (string) $result->getSubType());
        $this->assertSame(ArrayCollection::class, $result->getCollectionClass());
    }

    public function testMergeClassCollectionWithExplicitCollectionList(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $typeHintedCollection = new PropertyTypeClass(Collection::class, true);
        $explicitCollection = new PropertyTypeIterable($subType, false, false, ArrayCollection::class);

        $result = $typeHintedCollection->merge($explicitCollection);
        $this->assertInstanceOf(PropertyTypeIterable::class, $result);
        /* @var PropertyTypeIterable $result */
        $this->assertTrue($result->isCollection());
        $this->assertSame((string) $explicitCollection->getSubType(), (string) $result->getSubType());
        $this->assertSame(ArrayCollection::class, $result->getCollectionClass());
    }

    public function testMergeListAndCollection(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $list = new PropertyTypeIterable($subType, false, false);
        $collection = new PropertyTypeIterable($subType, false, false, Collection::class);

        foreach ([$list->merge($collection), $collection->merge($list)] as $result) {
            $this->assertInstanceOf(PropertyTypeIterable::class, $result);
            /* @var PropertyTypeIterable $result */
            $this->assertTrue($result->isCollection());
            $this->assertSame(Collection::class, $result->getCollectionClass());
            $this->assertSame((string) $list->getSubType(), (string) $result->getSubType());
        }
    }

    public function testMergeListWithExplicitCollectionClass(): void
    {
        $subType = new PropertyTypePrimitive('int', false);
        $list = new PropertyTypeIterable($subType, false, false);
        $collection = new PropertyTypeIterable($subType, false, false, ArrayCollection::class);

        foreach ([$list->merge($collection), $collection->merge($list)] as $result) {
            $this->assertInstanceOf(PropertyTypeIterable::class, $result);
            /* @var PropertyTypeIterable $result */
            $this->assertTrue($result->isCollection());
            $this->assertSame(ArrayCollection::class, $result->getCollectionClass());
            $this->assertSame((string) $list->getSubType(), (string) $result->getSubType());
        }
    }
}
