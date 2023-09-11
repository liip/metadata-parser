<?php

namespace Liip\MetadataParser\ModelParser;

use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\Metadata\PropertyAccessor;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;
use ReflectionClass;
use ReflectionException;

class VisibilityAwarePropertyAccessGuesser implements ModelParserInterface
{
    public function parse(RawClassMetadata $classMetadata): void
    {
        try {
            $reflClass = new ReflectionClass($classMetadata->getClassName());
        } catch (ReflectionException $e) {
            throw ParseException::classNotFound($classMetadata->getClassName(), $e);
        }

        $this->parseProperties($reflClass, $classMetadata);
    }

    public function parseProperties(ReflectionClass $reflClass, RawClassMetadata $classMetadata): void
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

            if (!($currentAccessor->getSetterMethod() && $currentAccessor->hasSetterMethod())) {
                $variation->setAccessor(new PropertyAccessor(
                    $currentAccessor->getGetterMethod() ?: $this->guessGetter($reflClass, $variation),
                    $currentAccessor->getSetterMethod() ?: $this->guessSetter($reflClass, $variation),
                ));
            }
        }
    }

    private function guessGetter(ReflectionClass $reflClass, PropertyVariationMetadata $variation): ?string
    {
        foreach (['get', 'is', ''] as $prefix) {
            $method = "$prefix{$variation->getName()}";

            if (!$reflClass->hasMethod($method)) continue;

            $reflMethod = $reflClass->getMethod($method);

            if ($reflMethod->isPublic() && ($reflMethod->getNumberOfRequiredParameters() === 0)) {
                return $method;
            }
        }

        return null;
    }

    private function guessSetter(ReflectionClass $reflClass, PropertyVariationMetadata $variation): ?string
    {
        foreach (['set', ''] as $prefix) {
            $method = "$prefix{$variation->getName()}";

            if (!$reflClass->hasMethod($method)) continue;

            $reflMethod = $reflClass->getMethod($method);

            if ($reflMethod->isPublic() && ($reflMethod->getNumberOfParameters() >= 1)) {
                return $method;
            }
        }

        return null;
    }
}
