<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser;

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\Reader;
use Liip\MetadataParser\Annotation\Preferred;
use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;

/**
 * Parse annotations provided by this library.
 *
 * Annotations are only seen on properties and methods that provide a virtual
 * property when they are available in the class metadata at the point when
 * this parser runs. No error is raised if a field is marked as preferred but
 * not included in the meta data.
 */
final class LiipMetadataAnnotationParser implements ModelParserInterface
{
    /**
     * @var Reader
     */
    private $annotationsReader;

    public function __construct(Reader $annotationsReader)
    {
        $this->annotationsReader = $annotationsReader;
    }

    public function parse(RawClassMetadata $classMetadata): void
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

            try {
                $annotations = $this->annotationsReader->getPropertyAnnotations($reflProperty);
            } catch (AnnotationException $e) {
                throw ParseException::propertyError((string) $classMetadata, $reflProperty->getName(), $e);
            }

            $property = $classMetadata->getPropertyVariation($reflProperty->getName());
            $this->parsePropertyAnnotations($classMetadata, $property, $annotations);
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

            try {
                $annotations = $this->annotationsReader->getMethodAnnotations($reflMethod);
            } catch (AnnotationException $e) {
                throw ParseException::propertyError((string) $classMetadata, $reflMethod->getName(), $e);
            }

            $property = $classMetadata->getPropertyVariation($this->getMethodName($reflMethod));
            $this->parsePropertyAnnotations($classMetadata, $property, $annotations);
        }
    }

    private function parsePropertyAnnotations(RawClassMetadata $classMetadata, PropertyVariationMetadata $property, array $annotations): void
    {
        foreach ($annotations as $annotation) {
            switch (true) {
                case $annotation instanceof Preferred:
                    $property->setPreferred(true);
                    break;

                default:
                    if (0 === strncmp('Liip\MetadataParser\\', \get_class($annotation), \mb_strlen('Liip\MetadataParser\\'))) {
                        // if there are annotations we can safely ignore, we need to explicitly ignore them
                        throw ParseException::unsupportedPropertyAnnotation((string) $classMetadata, (string) $property, \get_class($annotation));
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
}
