<?php

declare(strict_types=1);

namespace Liip\MetadataParser\TypeParser;

use Doctrine\Common\Collections\ArrayCollection;
use JMS\Serializer\Type\Parser;
use Liip\MetadataParser\Exception\InvalidTypeException;
use Liip\MetadataParser\Metadata\DateTimeOptions;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\Metadata\PropertyTypeDateTime;
use Liip\MetadataParser\Metadata\PropertyTypeEnum;
use Liip\MetadataParser\Metadata\PropertyTypeIterable;
use Liip\MetadataParser\Metadata\PropertyTypePrimitive;
use Liip\MetadataParser\Metadata\PropertyTypeUnknown;
use Liip\MetadataParser\Metadata\SerializationMode;

final class JMSTypeParser
{
    private const TYPE_ARRAY = 'array';
    private const TYPE_ENUM = 'enum';
    private const TYPE_ARRAY_COLLECTION = 'ArrayCollection';
    private const TYPE_GENERATOR = 'Generator';
    private const TYPE_ARRAY_ITERATOR = 'ArrayIterator';
    private const TYPE_ITERATOR = 'Iterator';
    private const TYPE_DATETIME_INTERFACE = 'DateTimeInterface';

    private Parser $jmsTypeParser;

    public function __construct()
    {
        $this->jmsTypeParser = new Parser();
    }

    public function parse(string $rawType, \ReflectionProperty|\ReflectionMethod|null $reflection = null, bool $isSubType = false): PropertyType
    {
        if ('' === $rawType) {
            return new PropertyTypeUnknown(true);
        }

        return $this->parseType($this->jmsTypeParser->parse($rawType), $reflection, $isSubType);
    }

    private function parseType(array $typeInfo, \ReflectionProperty|\ReflectionMethod|null $reflection, bool $isSubType = false): PropertyType
    {
        $typeInfo = array_merge(
            [
                'name' => null,
                'params' => [],
            ],
            $typeInfo
        );

        // JMS types are nullable except if it's a sub type (part of array)
        $nullable = !$isSubType;

        if (0 === \count($typeInfo['params']) && self::TYPE_ENUM !== $typeInfo['name']) {
            if (self::TYPE_ARRAY === $typeInfo['name']) {
                return new PropertyTypeIterable(new PropertyTypeUnknown(false), false, $nullable);
            }

            if (PropertyTypePrimitive::isTypePrimitive($typeInfo['name'])) {
                return new PropertyTypePrimitive($typeInfo['name'], $nullable);
            }
            if (PropertyTypeDateTime::isTypeDateTime($typeInfo['name'])) {
                return PropertyTypeDateTime::fromDateTimeClass($typeInfo['name'], $nullable);
            }

            return new PropertyTypeClass($typeInfo['name'], $nullable);
        }

        $traversableClass = $this->getTraversableClass($typeInfo['name']);
        if (self::TYPE_ARRAY === $typeInfo['name'] || $traversableClass) {
            if (1 === \count($typeInfo['params'])) {
                return new PropertyTypeIterable($this->parseType($typeInfo['params'][0], $reflection, true), false, $nullable, $traversableClass);
            }
            if (2 === \count($typeInfo['params'])) {
                return new PropertyTypeIterable($this->parseType($typeInfo['params'][1], $reflection, true), true, $nullable, $traversableClass);
            }

            throw new InvalidTypeException(\sprintf('JMS property type array can\'t have more than 2 parameters (%s)', var_export($typeInfo, true)));
        }

        if (PropertyTypeDateTime::isTypeDateTime($typeInfo['name']) || (self::TYPE_DATETIME_INTERFACE === $typeInfo['name'])) {
            // the case of datetime without params is already handled above, we know we have params
            $serializeFormat = $typeInfo['params'][0] ?: null;
            // {@link \JMS\Serializer\Handler\DateHandler} of jms/serializer defaults to using the serialization format as a deserialization format if none was supplied...
            $deserializeFormats = ($typeInfo['params'][2] ?? null) ?: $serializeFormat;
            // ... and always converts single strings to arrays
            $deserializeFormats = \is_string($deserializeFormats) ? [$deserializeFormats] : $deserializeFormats;
            // Jms defaults to DateTime when given DateTimeInterface despite the documentation saying DateTimeImmutable, {@see \JMS\Serializer\Handler\DateHandler} in jms/serializer
            $className = (self::TYPE_DATETIME_INTERFACE === $typeInfo['name']) ? \DateTime::class : $typeInfo['name'];

            return PropertyTypeDateTime::fromDateTimeClass(
                $className,
                $nullable,
                new DateTimeOptions(
                    $serializeFormat,
                    ($typeInfo['params'][1] ?? null) ?: null,
                    $deserializeFormats,
                )
            );
        }

        if (self::TYPE_ENUM === $typeInfo['name']) {
            $enumType = $this->getEnumType($typeInfo, $reflection);

            $serializationMode = $this->getEnumSerializationMode($enumType, $typeInfo['params']);

            return new PropertyTypeEnum($enumType, $nullable, $serializationMode);
        }

        throw new InvalidTypeException(\sprintf('Unknown JMS property found (%s)', var_export($typeInfo, true)));
    }

    private function getTraversableClass(string $name): ?string
    {
        switch ($name) {
            case self::TYPE_ARRAY_COLLECTION:
                return ArrayCollection::class;
            case self::TYPE_GENERATOR:
                return \Generator::class;
            case self::TYPE_ARRAY_ITERATOR:
            case self::TYPE_ITERATOR:
                return \ArrayIterator::class;
            default:
                return is_a($name, \Traversable::class, true) ? $name : null;
        }
    }

    private function getEnumSerializationMode(string $enumType, array $typeParams): ?SerializationMode
    {
        $mode = $typeParams[1] ?? null;
        if (null === $mode) {
            return null;
        }

        $serializationMode = SerializationMode::tryFrom($mode);
        if (SerializationMode::Value === $serializationMode && !is_a($enumType, \BackedEnum::class, true)) {
            throw new InvalidTypeException(\sprintf('The type "%s" is not a backed enum, thus you cannot use "value" as serialization mode for its value.', $enumType));
        }

        return $serializationMode;
    }

    private function getEnumType(array $typeInfo, \ReflectionProperty|\ReflectionMethod|null $reflection): string
    {
        $enumType = $typeInfo['params'][0] ?? null;
        $enumType = $enumType['name'] ?? $enumType;
        if (null !== $enumType) {
            return $enumType;
        }

        $class = null === $reflection ? null : $reflection::class;
        $type = match ($class) {
            \ReflectionMethod::class => $reflection->getReturnType(),
            \ReflectionProperty::class => $reflection->getType(),
            default => null,
        };

        if (!$type instanceof \ReflectionNamedType) {
            throw new InvalidTypeException('Can not determine enum type from reflection, please define one by using "enum<MyEnum>"');
        }

        return $type->getName();
    }
}
