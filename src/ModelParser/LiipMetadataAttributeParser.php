<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser;

use Liip\MetadataParser\Attribute\Preferred;
use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\ModelParser\NamingStrategy\PropertyNamingStrategyInterface;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;

/**
 * Parse attributes provided by this library.
 *
 * Attributes are only seen on properties and methods that provide a virtual
 * property when they are available in the class metadata at the point when
 * this parser runs. No error is raised if a field is marked as preferred but
 * not included in the meta data.
 */
final class LiipMetadataAttributeParser implements ModelParserInterface
{
    public function parse(RawClassMetadata $classMetadata, PropertyNamingStrategyInterface $propertyNamingStrategy): void
    {
        try {
            $reflClass = new \ReflectionClass($classMetadata->getClassName());
        } catch (\ReflectionException $e) {
            throw ParseException::classNotFound($classMetadata->getClassName(), $e);
        }

        $this->parseProperties($reflClass, $classMetadata);
        $this->parseMethods($reflClass, $classMetadata);
    }

    private function parseProperties(\ReflectionClass $reflClass, RawClassMetadata $classMetadata): void
    {
        if ($reflParentClass = $reflClass->getParentClass()) {
            $this->parseProperties($reflParentClass, $classMetadata);
        }

        foreach ($reflClass->getProperties() as $reflProperty) {
            if (!$classMetadata->hasPropertyVariation($reflProperty->getName())) {
                continue;
            }

            $attributes = $this->getAttributes($reflProperty);

            $property = $classMetadata->getPropertyVariation($reflProperty->getName());
            $this->parsePropertyAttributes($classMetadata, $property, $attributes);
        }
    }

    private function parseMethods(\ReflectionClass $reflClass, RawClassMetadata $classMetadata): void
    {
        if ($reflParentClass = $reflClass->getParentClass()) {
            $this->parseMethods($reflParentClass, $classMetadata);
        }

        foreach ($reflClass->getMethods() as $reflMethod) {
            if (false === $reflMethod->getDocComment()) {
                continue;
            }
            if (!$classMetadata->hasPropertyVariation($this->getMethodName($reflMethod))) {
                continue;
            }

            $attributes = $this->getAttributes($reflMethod);

            $property = $classMetadata->getPropertyVariation($this->getMethodName($reflMethod));
            $this->parsePropertyAttributes($classMetadata, $property, $attributes);
        }
    }

    private function parsePropertyAttributes(RawClassMetadata $classMetadata, PropertyVariationMetadata $property, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            switch (true) {
                case $attribute instanceof Preferred:
                    $property->setPreferred(true);
                    break;

                default:
                    if (0 === strncmp('Liip\MetadataParser\\', \get_class($attribute), mb_strlen('Liip\MetadataParser\\'))) {
                        // if there are attributes we can safely ignore, we need to explicitly ignore them
                        throw ParseException::unsupportedPropertyAttribute((string) $classMetadata, (string) $property, \get_class($attribute));
                    }
                    break;
            }
        }
    }

    private function getMethodName(\ReflectionMethod $reflMethod): string
    {
        $name = $reflMethod->getName();
        if (0 === strpos($name, 'get')) {
            $name = lcfirst(substr($name, 3));
        }

        return $name;
    }

    /**
     * @return object[]
     */
    private function getAttributes(\ReflectionProperty|\ReflectionMethod|\ReflectionClass $reflection): array
    {
        $attributes = $reflection->getAttributes();

        return array_map(
            static fn (\ReflectionAttribute $attribute) => $attribute->newInstance(),
            $attributes
        );
    }
}
