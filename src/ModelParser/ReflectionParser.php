<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser;

use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\Metadata\ParameterMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;
use Liip\MetadataParser\TypeParser\PhpTypeParser;

final class ReflectionParser implements ModelParserInterface
{
    /**
     * @var PhpTypeParser
     */
    private $typeParser;

    /**
     * Whether the PHP reflections support property type declarations.
     *
     * @var bool
     */
    private $reflectionSupportsPropertyType;

    public function __construct()
    {
        $this->typeParser = new PhpTypeParser();
        $this->reflectionSupportsPropertyType = version_compare(PHP_VERSION, '7.4', '>=');
    }

    public function parse(RawClassMetadata $classMetadata): void
    {
        try {
            $reflClass = new \ReflectionClass($classMetadata->getClassName());
        } catch (\ReflectionException $e) {
            throw ParseException::classNotFound($classMetadata->getClassName(), $e);
        }

        $this->parseProperties($reflClass, $classMetadata);
        $this->parseConstructor($reflClass, $classMetadata);
    }

    private function parseProperties(\ReflectionClass $reflClass, RawClassMetadata $classMetadata): void
    {
        if ($reflParentClass = $reflClass->getParentClass()) {
            $this->parseProperties($reflParentClass, $classMetadata);
        }

        foreach ($reflClass->getProperties() as $reflProperty) {
            $type = null;
            $reflectionType = $this->reflectionSupportsPropertyType ? $reflProperty->getType() : null;
            if ($reflectionType instanceof \ReflectionNamedType) {
                // If the field has a union type (since PHP 8.0) or intersection type (since PHP 8.1),
                // the type would be a different kind of ReflectionType than ReflectionNamedType.
                // We don't have support in the metadata model to handle multiple types.
                $type = $this->typeParser->parseReflectionType($reflectionType);
            }
            if ($classMetadata->hasPropertyVariation($reflProperty->getName())) {
                $property = $classMetadata->getPropertyVariation($reflProperty->getName());
                $property->setPublic($reflProperty->isPublic());
                if ($type) {
                    $property->setType($type);
                }
            } else {
                $property = PropertyVariationMetadata::fromReflection($reflProperty);
                if ($type) {
                    $property->setType($type);
                }
                $classMetadata->addPropertyVariation($reflProperty->getName(), $property);
            }
        }
    }

    private function parseConstructor(\ReflectionClass $reflClass, RawClassMetadata $classMetadata): void
    {
        $constructor = $reflClass->getConstructor();
        if (null === $constructor) {
            return;
        }

        foreach ($constructor->getParameters() as $reflParameter) {
            $classMetadata->addConstructorParameter(ParameterMetadata::fromReflection($reflParameter));
        }
    }
}
