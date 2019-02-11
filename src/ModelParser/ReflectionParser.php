<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser;

use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\Metadata\ParameterMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;

final class ReflectionParser implements ModelParserInterface
{
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
            if ($classMetadata->hasPropertyVariation($reflProperty->getName())) {
                $property = $classMetadata->getPropertyVariation($reflProperty->getName());
                $property->setPublic($reflProperty->isPublic());
            } else {
                $classMetadata->addPropertyVariation($reflProperty->getName(), PropertyVariationMetadata::fromReflection($reflProperty));
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
