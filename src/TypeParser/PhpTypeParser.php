<?php

declare(strict_types=1);

namespace Liip\MetadataParser\TypeParser;

use Doctrine\Common\Annotations\PhpParser;
use Liip\MetadataParser\Exception\InvalidTypeException;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeArray;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\Metadata\PropertyTypeDateTime;
use Liip\MetadataParser\Metadata\PropertyTypePrimitive;
use Liip\MetadataParser\Metadata\PropertyTypeUnknown;

final class PhpTypeParser
{
    private const TYPE_SEPARATOR = '|';
    private const TYPE_NULL = 'null';
    private const TYPE_MIXED = 'mixed';
    private const TYPE_RESOURCE = 'resource';
    private const TYPE_ARRAY = 'array';
    private const TYPE_ARRAY_SUFFIX = '[]';
    private const TYPE_HASHMAP_SUFFIX = '[string]';
    private const TYPES_GENERIC = [
        'object',
        'mixed',
    ];

    /**
     * @var PhpParser
     */
    private $useStatementsParser;

    public function __construct()
    {
        $this->useStatementsParser = new PhpParser();
    }

    /**
     * @throws InvalidTypeException if an invalid type or multiple types were defined
     */
    public function parseAnnotationType(string $rawType, \ReflectionClass $declaringClass): PropertyType
    {
        if ('' === $rawType) {
            return new PropertyTypeUnknown(true);
        }

        $types = [];
        $nullable = false;
        foreach (explode(self::TYPE_SEPARATOR, $rawType) as $part) {
            if (self::TYPE_NULL === $part || self::TYPE_MIXED === $part) {
                $nullable = true;
            } elseif (!\in_array($part, self::TYPES_GENERIC, true)) {
                $types[] = $part;
            }
        }

        if (0 === \count($types)) {
            return new PropertyTypeUnknown($nullable);
        }
        if (\count($types) > 1) {
            throw new InvalidTypeException(sprintf('Multiple types are not supported (%s)', $rawType));
        }

        return $this->createType($types[0], $nullable, $declaringClass);
    }

    /**
     * @throws InvalidTypeException if an invalid type was defined
     */
    public function parseReflectionType(\ReflectionType $reflType): PropertyType
    {
        if ($reflType instanceof \ReflectionNamedType) {
            return $this->createType($reflType->getName(), $reflType->allowsNull());
        }

        throw new InvalidTypeException(sprintf('No type information found, got %s but expected %s', \ReflectionType::class, \ReflectionNamedType::class));
    }

    private function createType(string $rawType, bool $nullable, \ReflectionClass $reflClass = null): PropertyType
    {
        if (self::TYPE_ARRAY === $rawType) {
            return new PropertyTypeArray(new PropertyTypeUnknown(false), false, $nullable);
        }

        if (self::TYPE_ARRAY_SUFFIX === substr($rawType, -\strlen(self::TYPE_ARRAY_SUFFIX))) {
            $rawSubType = substr($rawType, 0, \strlen($rawType) - \strlen(self::TYPE_ARRAY_SUFFIX));

            return new PropertyTypeArray($this->createType($rawSubType, false, $reflClass), false, $nullable);
        }
        if (self::TYPE_HASHMAP_SUFFIX === substr($rawType, -\strlen(self::TYPE_HASHMAP_SUFFIX))) {
            $rawSubType = substr($rawType, 0, \strlen($rawType) - \strlen(self::TYPE_HASHMAP_SUFFIX));

            return new PropertyTypeArray($this->createType($rawSubType, false, $reflClass), true, $nullable);
        }

        if (self::TYPE_RESOURCE === $rawType) {
            throw new InvalidTypeException('Type "resource" is not supported');
        }

        if (PropertyTypePrimitive::isTypePrimitive($rawType)) {
            return new PropertyTypePrimitive($rawType, $nullable);
        }

        $resolvedClass = $this->resolveClass($rawType, $reflClass);

        if (PropertyTypeDateTime::isTypeDateTime($resolvedClass)) {
            return PropertyTypeDateTime::fromDateTimeClass($resolvedClass, $nullable);
        }

        return new PropertyTypeClass($resolvedClass, $nullable);
    }

    private function resolveClass(string $className, \ReflectionClass $reflClass = null): string
    {
        // leading backslash means absolute class name
        if (0 === strpos($className, '\\')) {
            return substr($className, 1);
        }

        if (null !== $reflClass) {
            // resolve use statements of the class with the type information
            $lowerClassName = strtolower($className);

            $reflCurrentClass = $reflClass;
            do {
                $imports = $this->useStatementsParser->parseClass($reflCurrentClass);
                if (isset($imports[$lowerClassName])) {
                    return $imports[$lowerClassName];
                }
            } while (false !== ($reflCurrentClass = $reflCurrentClass->getParentClass()));

            foreach ($reflClass->getTraits() as $reflTrait) {
                $imports = $this->useStatementsParser->parseClass($reflTrait);
                if (isset($imports[$lowerClassName])) {
                    return $imports[$lowerClassName];
                }
            }

            // the referenced class is expected to be in the same namespace
            $namespace = $reflClass->getNamespaceName();
            if ('' !== $namespace) {
                return $namespace.'\\'.$className;
            }
        }

        // edge case of models defined in the global namespace
        return $className;
    }
}
