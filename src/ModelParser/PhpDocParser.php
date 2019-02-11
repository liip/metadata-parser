<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser;

use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeUnknown;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;
use Liip\MetadataParser\TypeParser\PhpTypeParser;

final class PhpDocParser implements ModelParserInterface
{
    /**
     * @var PhpTypeParser
     */
    private $typeParser;

    public function __construct()
    {
        $this->typeParser = new PhpTypeParser();
    }

    public function parse(RawClassMetadata $classMetadata): void
    {
        try {
            $reflClass = new \ReflectionClass($classMetadata->getClassName());
        } catch (\ReflectionException $e) {
            throw ParseException::classNotFound($classMetadata->getClassName(), $e);
        }

        $this->parseProperties($reflClass, $classMetadata);
    }

    private function parseProperties(\ReflectionClass $reflClass, RawClassMetadata $classMetadata): void
    {
        if ($reflParentClass = $reflClass->getParentClass()) {
            $this->parseProperties($reflParentClass, $classMetadata);
        }

        foreach ($reflClass->getProperties() as $reflProperty) {
            if ($classMetadata->hasPropertyVariation($reflProperty->getName())) {
                $property = $classMetadata->getPropertyVariation($reflProperty->getName());
            } else {
                $property = PropertyVariationMetadata::fromReflection($reflProperty);
                $classMetadata->addPropertyVariation($reflProperty->getName(), $property);
            }

            $docComment = $reflProperty->getDocComment();
            if (false !== $docComment && $property->getType() instanceof PropertyTypeUnknown) {
                try {
                    $type = $this->getPropertyTypeFromDocComment($docComment, $reflClass);
                } catch (ParseException $e) {
                    throw ParseException::propertyTypeError((string) $classMetadata, (string) $property, $e);
                }
                if (null !== $type) {
                    $property->setType($type);
                }
            }
        }
    }

    private function getPropertyTypeFromDocComment(string $docComment, \ReflectionClass $reflClass): ?PropertyType
    {
        foreach (explode("\n", $docComment) as $line) {
            if (1 === preg_match('/@var ([^ ]+)/', $line, $matches)) {
                return $this->typeParser->parseAnnotationType($matches[1], $reflClass);
            }
        }

        return null;
    }
}
