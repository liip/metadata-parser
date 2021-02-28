<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\Metadata;

use Liip\MetadataParser\Metadata\ClassMetadata;
use Liip\MetadataParser\Metadata\ParameterMetadata;
use Liip\MetadataParser\Metadata\PropertyMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;
use PHPUnit\Framework\TestCase;

/**
 * @small
 */
class ClassMetadataTest extends TestCase
{
    public function testFromRaw(): void
    {
        $rawClassMetadata = new RawClassMetadata('Foo');
        $rawClassMetadata->addConstructorParameter(new ParameterMetadata('arg', true));
        $rawClassMetadata->addConstructorParameter(new ParameterMetadata('withDefault', false, 'default'));
        $rawClassMetadata->addConstructorParameter(new ParameterMetadata('optional', false));
        $rawClassMetadata->addPostDeserializeMethod('postDeserialize');
        $properties = [new PropertyMetadata('test', 'testProperty')];

        $classMetadata = ClassMetadata::fromRawClassMetadata($rawClassMetadata, $properties);
        static::assertSame('Foo', $classMetadata->getClassName());

        $constructorParameters = $classMetadata->getConstructorParameters();
        static::assertCount(3, $constructorParameters);
        static::assertSame('arg', $constructorParameters[0]->getName());
        static::assertSame('withDefault', $constructorParameters[1]->getName());
        static::assertSame('optional', $constructorParameters[2]->getName());

        $postDeserializeMethods = $classMetadata->getPostDeserializeMethods();
        static::assertCount(1, $postDeserializeMethods);
        static::assertSame('postDeserialize', $postDeserializeMethods[0]);

        $props = $classMetadata->getProperties();
        static::assertCount(1, $props);
        static::assertSame('testProperty', $props[0]->getName());
    }
}
