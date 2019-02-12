<?php

declare(strict_types=1);

namespace Liip\MetadataParser\TypeParser;

use JMS\Serializer\Type\Parser as TypeParserV2;
use JMS\Serializer\TypeParser as TypeParserV1;
use Liip\MetadataParser\Exception\InvalidTypeException;
use Liip\MetadataParser\Metadata\DateTimeOptions;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeArray;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\Metadata\PropertyTypeDateTime;
use Liip\MetadataParser\Metadata\PropertyTypePrimitive;
use Liip\MetadataParser\Metadata\PropertyTypeUnknown;

final class JMSTypeParser
{
    private const TYPE_ARRAY = 'array';

    /**
     * @var TypeParserV1|TypeParserV2
     */
    private $jmsTypeParser;

    public function __construct()
    {
        $this->jmsTypeParser = class_exists(TypeParserV2::class) ? new TypeParserV2() : new TypeParserV1();
    }

    /**
     * @throws InvalidTypeException
     */
    public function parse(string $rawType): PropertyType
    {
        if ('' === $rawType) {
            return new PropertyTypeUnknown(true);
        }

        return $this->parseType($this->jmsTypeParser->parse($rawType));
    }

    private function parseType(array $typeInfo, bool $isSubType = false): PropertyType
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

        if (0 === \count($typeInfo['params'])) {
            if (self::TYPE_ARRAY === $typeInfo['name']) {
                return new PropertyTypeArray(new PropertyTypeUnknown(false), false, $nullable);
            }

            if (PropertyTypePrimitive::isTypePrimitive($typeInfo['name'])) {
                return new PropertyTypePrimitive($typeInfo['name'], $nullable);
            }
            if (PropertyTypeDateTime::isTypeDateTime($typeInfo['name'])) {
                return PropertyTypeDateTime::fromDateTimeClass($typeInfo['name'], $nullable);
            }

            return new PropertyTypeClass($typeInfo['name'], $nullable);
        }

        if (self::TYPE_ARRAY === $typeInfo['name']) {
            if (1 === \count($typeInfo['params'])) {
                return new PropertyTypeArray($this->parseType($typeInfo['params'][0], true), false, $nullable);
            }
            if (2 === \count($typeInfo['params'])) {
                return new PropertyTypeArray($this->parseType($typeInfo['params'][1], true), true, $nullable);
            }

            throw new InvalidTypeException(sprintf('JMS property type array can\'t have more than 2 parameters (%s)', var_export($typeInfo, true)));
        }

        if (PropertyTypeDateTime::isTypeDateTime($typeInfo['name'])) {
            // the case of datetime without params is already handled above, we know we have params
            return PropertyTypeDateTime::fromDateTimeClass(
                $typeInfo['name'],
                $nullable,
                new DateTimeOptions(
                    $typeInfo['params'][0] ?: null,
                    ($typeInfo['params'][1] ?? null) ?: null,
                    ($typeInfo['params'][2] ?? null) ?: null
                )
            );
        }

        throw new InvalidTypeException(sprintf('Unknown JMS property found (%s)', var_export($typeInfo, true)));
    }
}
