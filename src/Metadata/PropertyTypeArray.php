<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

use Doctrine\Common\Collections\Collection;

/**
 * @deprecated Please use {@see PropertyTypeIterable} instead
 */
class PropertyTypeArray extends AbstractPropertyType
{
    /**
     * @var PropertyType
     */
    protected $subType;

    /**
     * @var bool
     */
    protected $hashmap;

    /**
     * @var bool
     */
    protected $isCollection;

    public function __construct(PropertyType $subType, bool $hashmap, bool $nullable, bool $isCollection = false)
    {
        parent::__construct($nullable);

        $this->subType = $subType;
        $this->hashmap = $hashmap;
        $this->isCollection = $isCollection;
    }

    public function __toString(): string
    {
        if ($this->subType instanceof PropertyTypeUnknown) {
            return 'array'.($this->isCollection ? '|\\'.Collection::class : '');
        }

        $array = $this->isHashmap() ? '[string]' : '[]';
        if ($this->isCollection) {
            $collectionType = $this->isHashmap() ? ', string' : '';
            $array .= sprintf('|\\%s<%s%s>', Collection::class, $this->subType, $collectionType);
        }

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
     * @deprecated Please prefer using {@link isTraversable}
     */
    public function isCollection(): bool
    {
        return $this->isCollection;
    }

    /**
     * @deprecated Please prefer using {@link getTraversableClass}
     *
     * @return class-string<Collection>|null
     */
    public function getCollectionClass(): ?string
    {
        return $this->isCollection() ? Collection::class : null;
    }

    public function isTraversable(): bool
    {
        return $this->isCollection;
    }

    /**
     * @return class-string<\Traversable>
     */
    public function getTraversableClass(): string
    {
        if (!$this->isTraversable()) {
            throw new \UnexpectedValueException("Iterable type '{$this}' is not traversable.");
        }

        return \Traversable::class;
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
        if ($this->isCollection() && (($other instanceof PropertyTypeClass) && is_a($other->getClassName(), Collection::class, true))) {
            return new self($this->getSubType(), $this->isHashmap(), $nullable, true);
        }
        if (!$other instanceof self) {
            throw new \UnexpectedValueException(sprintf('Can\'t merge type %s with %s, they must be the same or unknown', self::class, \get_class($other)));
        }

        /*
         * We allow converting array to hashmap (but not the other way round).
         *
         * PHPDoc has no clear definition for hashmaps with string indexes, but JMS Serializer annotations do.
         */
        if ($this->isHashmap() && !$other->isHashmap()) {
            throw new \UnexpectedValueException(sprintf('Can\'t merge type %s with %s, can\'t change hashmap into plain array', self::class, \get_class($other)));
        }

        $hashmap = $this->isHashmap() || $other->isHashmap();
        $isCollection = $this->isCollection || $other->isCollection;
        if ($other->getSubType() instanceof PropertyTypeUnknown) {
            return new self($this->getSubType(), $hashmap, $nullable, $isCollection);
        }
        if ($this->getSubType() instanceof PropertyTypeUnknown) {
            return new self($other->getSubType(), $hashmap, $nullable, $isCollection);
        }

        return new self($this->getSubType()->merge($other->getSubType()), $hashmap, $nullable, $isCollection);
    }
}
