<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser;

use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeUnknown;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyCollection;
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

    /**
     * @return string[] the property names that have been added
     */
    private function parseProperties(\ReflectionClass $reflClass, RawClassMetadata $classMetadata): array
    {
        $existingProperties = array_map(static function (PropertyCollection $prop): string {
            return (string) $prop;
        }, $classMetadata->getPropertyCollections());

        $addedProperties = [];
        $parentProperties = [];
        if ($reflParentClass = $reflClass->getParentClass()) {
            $parentProperties = $this->parseProperties($reflParentClass, $classMetadata);
        }
        $parentPropertiesLookup = array_flip($parentProperties);

        foreach ($reflClass->getProperties() as $reflProperty) {
            if ($classMetadata->hasPropertyVariation($reflProperty->getName())) {
                $property = $classMetadata->getPropertyVariation($reflProperty->getName());
            } else {
                $property = PropertyVariationMetadata::fromReflection($reflProperty);
                $classMetadata->addPropertyVariation($reflProperty->getName(), $property);
            }

            $docComment = $reflProperty->getDocComment();
            if (false !== $docComment) {
                try {
                    $type = $this->getPropertyTypeFromDocComment($docComment, $reflProperty);
                } catch (ParseException $e) {
                    throw ParseException::propertyTypeError((string) $classMetadata, (string) $property, $e);
                }
                if (null === $type) {
                    continue;
                }

                if ($property->getType() instanceof PropertyTypeUnknown || \array_key_exists((string) $property, $parentPropertiesLookup)) {
                    $property->setType($type);
                } else {
                    try {
                        $property->setType($property->getType()->merge($type));
                    } catch (\UnexpectedValueException $e) {
                        throw ParseException::propertyTypeConflict((string) $classMetadata, (string) $property, (string) $property->getType(), (string) $type, $e);
                    }
                }
                $addedProperties[] = (string) $property;
            }
        }

        return array_values(array_diff(array_unique(array_merge($parentProperties, $addedProperties)), $existingProperties));
    }

    private function getPropertyTypeFromDocComment(string $docComment, \ReflectionProperty $reflProperty): ?PropertyType
    {
        foreach (explode("\n", $docComment) as $line) {
            if (1 === preg_match('/@var ([^ ]+)/', $line, $matches)) {
                return $this->typeParser->parseAnnotationType($matches[1], $reflProperty->getDeclaringClass());
            }
        }

        return null;
    }
}
