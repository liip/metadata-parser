<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\Metadata;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Liip\MetadataParser\Metadata\PropertyTypeArray;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
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

        $this->assertInstanceOf(PropertyTypeArray::class, $propertyType->getSubType());
        $this->assertInstanceOf(PropertyTypePrimitive::class, $propertyType->getSubType()->getSubType());
        $this->assertInstanceOf(PropertyTypePrimitive::class, $propertyType->getLeafType());
    }

    public function testDefaultListIsNotCollection()
    {
        $subType = new PropertyTypePrimitive('int', false);
        $list = new PropertyTypeArray($subType, false, false);

        $this->assertFalse($list->isCollection());
    }

    public function testDefaultCollectionClassIsCollectionInterface()
    {
        $subType = new PropertyTypePrimitive('int', false);
        $collection = new PropertyTypeArray($subType, false, false, true);

        $this->assertTrue($collection->isCollection());
        $this->assertEquals(Collection::class, $collection->getCollectionClass());
    }

    public function testExplicitCollectionClassIsKept()
    {
        $subType = new PropertyTypePrimitive('int', false);

        // the $isCollection parameter of {@see PropertyTypeArray::__construct} is only kept for BC,
        // here we ensure that the metadata still says it's a collection even when that parameter isn't set.
        foreach ([true, false] as $isCollection) {
            $collection = new PropertyTypeArray($subType, false, false, $isCollection, ArrayCollection::class);

            $this->assertTrue($collection->isCollection());
            $this->assertNotEquals(Collection::class, $collection->getCollectionClass());
            $this->assertEquals(ArrayCollection::class, $collection->getCollectionClass());
        }
    }

    public function testMergeListWithClassCollection()
    {
        $subType = new PropertyTypePrimitive('int', false);
        $list = new PropertyTypeArray($subType, false, false);

        $this->expectException(\UnexpectedValueException::class);
        $list->merge(new PropertyTypeClass(Collection::class, true));
    }

    public function testMergeDefaultCollectionListWithClassCollection()
    {
        $subType = new PropertyTypePrimitive('int', false);
        $typeHintedCollection = new PropertyTypeClass(Collection::class, true);
        $defaultCollection = new PropertyTypeArray($subType, false, false, true);

        $result = $defaultCollection->merge($typeHintedCollection);
        $this->assertInstanceOf(PropertyTypeArray::class, $result);
        /** @var PropertyTypeArray $result */
        $this->assertTrue($result->isCollection());
        $this->assertEquals($defaultCollection->getSubType(), $result->getSubType());
        $this->assertEquals(Collection::class, $result->getCollectionClass());
    }

    public function testMergeExplicitCollectionListWithClassCollection()
    {
        $subType = new PropertyTypePrimitive('int', false);
        $typeHintedCollection = new PropertyTypeClass(Collection::class, true);
        $explicitCollection = new PropertyTypeArray($subType, false, false, true, ArrayCollection::class);

        $result = $explicitCollection->merge($typeHintedCollection);
        $this->assertInstanceOf(PropertyTypeArray::class, $result);
        /** @var PropertyTypeArray $result */
        $this->assertTrue($result->isCollection());
        $this->assertEquals($explicitCollection->getSubType(), $result->getSubType());
        $this->assertEquals(ArrayCollection::class, $result->getCollectionClass());
    }

    public function testMergeExplicitCollectionListWithDefaultCollection()
    {
        $subType = new PropertyTypePrimitive('int', false);
        $defaultCollection = new PropertyTypeArray($subType, false, false, true);
        $explicitCollection = new PropertyTypeArray($subType, false, false, true, ArrayCollection::class);

        $result = $explicitCollection->merge($defaultCollection);
        $this->assertInstanceOf(PropertyTypeArray::class, $result);
        /** @var PropertyTypeArray $result */
        $this->assertTrue($result->isCollection());
        $this->assertEquals($explicitCollection->getSubType(), $result->getSubType());
        $this->assertEquals(ArrayCollection::class, $result->getCollectionClass());
    }

    public function testMergeDefaultCollectionListWithExplicitCollection()
    {
        $subType = new PropertyTypePrimitive('int', false);
        $defaultCollection = new PropertyTypeArray($subType, false, false, true);
        $explicitCollection = new PropertyTypeArray($subType, false, false, true, ArrayCollection::class);

        $result = $defaultCollection->merge($explicitCollection);
        $this->assertInstanceOf(PropertyTypeArray::class, $result);
        /** @var PropertyTypeArray $result */
        $this->assertTrue($result->isCollection());
        $this->assertEquals($explicitCollection->getSubType(), $result->getSubType());
        $this->assertEquals(ArrayCollection::class, $result->getCollectionClass());
    }

    public function testMergeClassCollectionWithExplicitCollectionList()
    {
        $subType = new PropertyTypePrimitive('int', false);
        $typeHintedCollection = new PropertyTypeClass(Collection::class, true);
        $explicitCollection = new PropertyTypeArray($subType, false, false, true, ArrayCollection::class);

        $result = $typeHintedCollection->merge($explicitCollection);
        $this->assertInstanceOf(PropertyTypeArray::class, $result);
        /** @var PropertyTypeArray $result */
        $this->assertTrue($result->isCollection());
        $this->assertEquals($explicitCollection->getSubType(), $result->getSubType());
        $this->assertEquals(ArrayCollection::class, $result->getCollectionClass());
    }

    public function testMergeListAndCollection()
    {
        $subType = new PropertyTypePrimitive('int', false);
        $list = new PropertyTypeArray($subType, false, false);
        $collection = new PropertyTypeArray($subType, false, false, true);

        foreach ([$list->merge($collection), $collection->merge($list)] as $result) {
            $this->assertInstanceOf(PropertyTypeArray::class, $result);
            /** @var PropertyTypeArray $result */
            $this->assertTrue($result->isCollection());
            $this->assertEquals(Collection::class, $result->getCollectionClass());
            $this->assertEquals($list->getSubType(), $result->getSubType());
        }
    }

    public function testMergeListWithExplicitCollectionClass()
    {
        $subType = new PropertyTypePrimitive('int', false);
        $list = new PropertyTypeArray($subType, false, false);
        $collection = new PropertyTypeArray($subType, false, false, true, ArrayCollection::class);

        foreach ([$list->merge($collection), $collection->merge($list)] as $result) {
            $this->assertInstanceOf(PropertyTypeArray::class, $result);
            /** @var PropertyTypeArray $result */
            $this->assertTrue($result->isCollection());
            $this->assertEquals(ArrayCollection::class, $result->getCollectionClass());
            $this->assertEquals($list->getSubType(), $result->getSubType());
        }
    }
}
