<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

final class PropertyTypeArray extends AbstractPropertyType
{
    /**
     * @var PropertyType
     */
    private $subType;

    /**
     * @var bool
     */
    private $hashmap;

    public function __construct(PropertyType $subType, bool $hashmap, bool $nullable)
    {
        parent::__construct($nullable);

        $this->subType = $subType;
        $this->hashmap = $hashmap;
    }

    public function __toString(): string
    {
        if ($this->subType instanceof PropertyTypeUnknown) {
            return 'array';
        }
        $array = $this->isHashmap() ? '[string]' : '[]';

        return ((string) $this->subType).$array.parent::__toString();
    }

    public function isHashmap(): bool
    {
        return $this->hashmap;
    }

    /**
     * Returns the type of the next level, which could be an array or hashmap or another type.
     */
    public function getSubType(): PropertyType
    {
        return $this->subType;
    }

    /**
     * Goes down the type until it is not an array or hashmap anymore.
     */
    public function getLeafType(): PropertyType
    {
        $type = $this->getSubType();
        while ($type instanceof self) {
            $type = $type->getSubType();
        }

        return $type;
    }

    public function merge(PropertyType $other): PropertyType
    {
        $nullable = $this->isNullable() && $other->isNullable();

        if ($other instanceof PropertyTypeUnknown) {
            return new self($this->subType, $this->isHashmap(), $nullable);
        }
        if (!$other instanceof self) {
            throw new \UnexpectedValueException(sprintf('Can\'t merge type %s with %s, they must be the same or unknown', static::class, \get_class($other)));
        }

        /*
         * We allow converting array to hashmap (but not the other way round).
         *
         * PHPDoc has no clear definition for hashmaps with string indexes, but JMS Serializer annotations do.
         */
        if ($this->isHashmap() && !$other->isHashmap()) {
            throw new \UnexpectedValueException(sprintf('Can\'t merge type %s with %s, can\'t change hashmap into plain array', static::class, \get_class($other)));
        }

        $hashmap = $this->isHashmap() || $other->isHashmap();
        if ($other->getSubType() instanceof PropertyTypeUnknown) {
            return new self($this->getSubType(), $hashmap, $nullable);
        }
        if ($this->getSubType() instanceof PropertyTypeUnknown) {
            return new self($other->getSubType(), $hashmap, $nullable);
        }

        return new self($this->getSubType()->merge($other->getSubType()), $hashmap, $nullable);
    }
}
