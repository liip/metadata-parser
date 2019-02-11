<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser;

use Liip\MetadataParser\Exception\RecursionException;
use Liip\MetadataParser\Metadata\ClassMetadata;
use Liip\MetadataParser\Metadata\PropertyMetadata;
use Liip\MetadataParser\Metadata\PropertyTypeArray;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\RecursionChecker;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\Liip\MetadataParser\ModelParser\Model\Nested;
use Tests\Liip\MetadataParser\ModelParser\Model\Recursion;

/**
 * @small
 */
class RecursionCheckerTest extends TestCase
{
    public function testNoRecursion(): void
    {
        $customClassType = new PropertyTypeClass(Nested::class, false);
        $customClassType->setClassMetadata(new ClassMetadata(Nested::class, []));
        $classMetadata = new ClassMetadata(
            'Root',
            [
                new PropertyMetadata(
                    'property',
                    'property',
                    $customClassType
                ),
            ]
        );

        $metadata = $this->createChecker()->check($classMetadata);

        $this->assertCount(1, $metadata->getProperties());
    }

    public function testRecursion(): void
    {
        $customClassType = new PropertyTypeClass(Recursion::class, true);
        $classMetadata = new ClassMetadata(
            Recursion::class,
            [
                new PropertyMetadata(
                    'property',
                    'property',
                    $customClassType
                ),
            ]
        );
        $customClassType->setClassMetadata($classMetadata);

        $this->expectException(RecursionException::class);
        $this->createChecker()->check($classMetadata);
    }

    public function testRecursionOnArray(): void
    {
        $customClassType = new PropertyTypeClass(Recursion::class, true);
        $classMetadata = new ClassMetadata(
            Recursion::class,
            [
                new PropertyMetadata(
                    'property',
                    'property',
                    new PropertyTypeArray($customClassType, false, true)
                ),
            ]
        );
        $customClassType->setClassMetadata($classMetadata);

        $this->expectException(RecursionException::class);
        $this->createChecker()->check($classMetadata);
    }

    public function testRecursionWithGaps(): void
    {
        $innerCustomClassType = new PropertyTypeClass(Recursion::class, true);
        $recursionClassMetadata = new ClassMetadata(
            Recursion::class,
            [
                new PropertyMetadata(
                    'property',
                    'property',
                    $innerCustomClassType
                ),
            ]
        );
        $innerCustomClassType->setClassMetadata($recursionClassMetadata);

        $outerCustomClassType = new PropertyTypeClass(Recursion::class, false);
        $outerCustomClassType->setClassMetadata($recursionClassMetadata);
        $classMetadata = new ClassMetadata(
            'Root',
            [
                new PropertyMetadata(
                    'property',
                    'property',
                    $outerCustomClassType
                ),
            ]
        );

        $this->expectException(RecursionException::class);
        $this->createChecker()->check($classMetadata);
    }

    public function testExpectedRecursion(): void
    {
        $innerCustomClassType = new PropertyTypeClass(Recursion::class, true);
        $recursionClassMetadata = new ClassMetadata(
            Recursion::class,
            [
                new PropertyMetadata(
                    'sub_property',
                    'subProperty',
                    $innerCustomClassType
                ),
            ]
        );
        $innerCustomClassType->setClassMetadata($recursionClassMetadata);

        $outerCustomClassType = new PropertyTypeClass(Recursion::class, false);
        $outerCustomClassType->setClassMetadata($recursionClassMetadata);
        $classMetadata = new ClassMetadata(
            'Root',
            [
                new PropertyMetadata(
                    'property',
                    'property',
                    $outerCustomClassType
                ),
            ]
        );

        $checker = $this->createChecker([
            ['Root', 'property', 'sub_property'],
        ]);
        $metadata = $checker->check($classMetadata);

        /** @var PropertyTypeClass $type */
        $type = $metadata->getProperties()[0]->getType();
        $this->assertInstanceOf(PropertyTypeClass::class, $type);
        $this->assertCount(0, $type->getClassMetadata()->getProperties());
    }

    public function testExpectedRecursionArray(): void
    {
        $innerCustomClassType = new PropertyTypeClass(Recursion::class, true);
        $recursionClassMetadata = new ClassMetadata(
            Recursion::class,
            [
                new PropertyMetadata(
                    'sub_property',
                    'subProperty',
                    $innerCustomClassType
                ),
            ]
        );
        $innerCustomClassType->setClassMetadata($recursionClassMetadata);

        $outerCustomClassType = new PropertyTypeClass(Recursion::class, false);
        $outerCustomClassType->setClassMetadata($recursionClassMetadata);
        $classMetadata = new ClassMetadata(
            'Root',
            [
                new PropertyMetadata(
                    'property',
                    'property',
                    new PropertyTypeArray($outerCustomClassType, false, true)
                ),
            ]
        );

        $checker = $this->createChecker([
            ['Root', 'property', 'sub_property'],
        ]);
        $metadata = $checker->check($classMetadata);

        /** @var PropertyTypeArray $type */
        $type = $metadata->getProperties()[0]->getType();
        $this->assertInstanceOf(PropertyTypeArray::class, $type);
        /** @var PropertyTypeClass $subType */
        $subType = $type->getLeafType();
        $this->assertInstanceOf(PropertyTypeClass::class, $subType);
        $this->assertCount(0, $subType->getClassMetadata()->getProperties());
    }

    private function createChecker(array $expectedRecursions = []): RecursionChecker
    {
        return new RecursionChecker($this->createMock(LoggerInterface::class), $expectedRecursions);
    }
}
