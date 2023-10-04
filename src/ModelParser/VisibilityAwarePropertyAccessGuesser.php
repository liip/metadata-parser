<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser;

use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\Metadata\PropertyAccessor;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;

/**
 * Run this after all properties have been added by other parsers. This parser will modify existing property metadata
 * referring to private properties by adding accessors if there are none. Said accessors are guessed from common
 * getter/setter naming schemes such as `get{$property}`, `set{$property}`
 */
class VisibilityAwarePropertyAccessGuesser implements ModelParserInterface
{
    public function parse(RawClassMetadata $classMetadata): void
    {
        try {
            $reflClass = new \ReflectionClass($classMetadata->getClassName());
        } catch (\ReflectionException $e) {
            throw ParseException::classNotFound($classMetadata->getClassName(), $e);
        }

        $this->parseProperties($reflClass, $classMetadata);
    }

    public function parseProperties(\ReflectionClass $reflClass, RawClassMetadata $classMetadata): void
    {
        if ($reflParentClass = $reflClass->getParentClass()) {
            $this->parseProperties($reflParentClass, $classMetadata);
        }

        foreach ($reflClass->getProperties() as $property) {
            if ($property->isPublic() || !$classMetadata->hasPropertyVariation($property->getName())) {
                continue;
            }

            $variation = $classMetadata->getPropertyVariation($property->getName());
            $currentAccessor = $variation->getAccessor();

            if (!($currentAccessor->hasGetterMethod() && $currentAccessor->hasSetterMethod())) {
                $variation->setAccessor(new PropertyAccessor(
                    $currentAccessor->getGetterMethod() ?: $this->guessGetter($reflClass, $variation),
                    $currentAccessor->getSetterMethod() ?: $this->guessSetter($reflClass, $variation),
                ));
            }
        }
    }

    /**
     * Find a getter method for property, using prefixes `get`, `is`, or simply no prefix
     */
    private function guessGetter(\ReflectionClass $reflClass, PropertyVariationMetadata $variation): ?string
    {
        foreach (['get', 'is', ''] as $prefix) {
            $method = "{$prefix}{$variation->getName()}";

            if (!$reflClass->hasMethod($method)) {
                continue;
            }

            $reflMethod = $reflClass->getMethod($method);

            if ($reflMethod->isPublic() && (0 === $reflMethod->getNumberOfRequiredParameters())) {
                return $method;
            }
        }

        return null;
    }

    /**
     * Find a setter method for property, using prefix `set`, or simply no prefix
     */
    private function guessSetter(\ReflectionClass $reflClass, PropertyVariationMetadata $variation): ?string
    {
        foreach (['set', ''] as $prefix) {
            $method = "{$prefix}{$variation->getName()}";

            if (!$reflClass->hasMethod($method)) {
                continue;
            }

            $reflMethod = $reflClass->getMethod($method);

            if ($reflMethod->isPublic() && ($reflMethod->getNumberOfRequiredParameters() == 1)) {
                return $method;
            }
        }

        return null;
    }
}
