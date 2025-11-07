<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser;

use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\Metadata\ParameterMetadata;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeUnion;
use Liip\MetadataParser\ModelParser\NamingStrategy\PropertyNamingStrategyInterface;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;
use Liip\MetadataParser\TypeParser\PhpTypeParser;

final class ReflectionParser implements ModelParserInterface
{
    private const SUPPORTED_UNION_TYPES = [
        'int',
        'float',
        'double',
        'bool',
        'true',
        'false',
        'string',
        'null',
        'array',
    ];

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
        $this->reflectionSupportsPropertyType = version_compare(\PHP_VERSION, '7.4', '>=');
    }

    public function parse(RawClassMetadata $classMetadata, PropertyNamingStrategyInterface $propertyNamingStrategy): void
    {
        try {
            $reflClass = new \ReflectionClass($classMetadata->getClassName());
        } catch (\ReflectionException $e) {
            throw ParseException::classNotFound($classMetadata->getClassName(), $e);
        }

        $this->parseProperties($reflClass, $classMetadata, $propertyNamingStrategy);
        $this->parseConstructor($reflClass, $classMetadata);
    }

    private function parseProperties(\ReflectionClass $reflClass, RawClassMetadata $classMetadata, PropertyNamingStrategyInterface $propertyNamingStrategy): void
    {
        if ($reflParentClass = $reflClass->getParentClass()) {
            $this->parseProperties($reflParentClass, $classMetadata, $propertyNamingStrategy);
        }

        foreach ($reflClass->getProperties() as $reflProperty) {
            $type = null;
            $reflectionType = $this->reflectionSupportsPropertyType ? $reflProperty->getType() : null;
            switch (true) {
                case $reflectionType instanceof \ReflectionNamedType:
                    $type = $this->typeParser->parseReflectionType($reflectionType);
                    break;
                case $reflectionType instanceof \ReflectionUnionType:
                    $types = $this->getSupportedUnionTypes($reflectionType);
                    if (\count($types) > 1) {
                        $types = array_map(fn (\ReflectionType $namedType): PropertyType => $this->typeParser->parseReflectionType($namedType), $types);
                        $type = new PropertyTypeUnion($types, $reflectionType->allowsNull());
                    }

                    break;
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
                $serializedName = $propertyNamingStrategy->getSerializedName($reflProperty->getName());
                $classMetadata->addPropertyVariation($serializedName, $property);
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

    /**
     * @return \ReflectionType[]
     */
    private function getSupportedUnionTypes(\ReflectionUnionType $reflectionUnionType): array
    {
        $supportedTypes = [];
        foreach ($reflectionUnionType->getTypes() as $type) {
            if (\in_array($type->getName(), self::SUPPORTED_UNION_TYPES, true)) {
                $supportedTypes[] = $type;
            }
        }

        return $supportedTypes;
    }
}
