<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

/**
 * This property type can be merged with PropertyTypeClass<T>, provided that T is, inherits from, or is a parent class of {@see PropertyTypeIterable::traversableClass}
 * This property type can be merged with PropertyTypeIterable, if :
 *  - we're not merging a plain array PropertyTypeIterable into a hashmap one,
 *  - and the traversable classes of each are either not present on either sides, or are the same, or parent-child of one another
 */
final class PropertyTypeIterable extends AbstractPropertyType
{
    /**
     * @var PropertyType
     */
    private $subType;

    /**
     * @var bool
     */
    private $hashmap;

    /**
     * @var string
     */
    private $traversableClass;

    /**
     * @param class-string<\Traversable>|null $traversableClass
     */
    public function __construct(PropertyType $subType, bool $hashmap, bool $nullable, string $traversableClass = null)
    {
        parent::__construct($nullable);

        $this->subType = $subType;
        $this->hashmap = $hashmap;
        $this->traversableClass = $traversableClass;
    }

    public function __toString(): string
    {
        if ($this->subType instanceof PropertyTypeUnknown) {
            return 'array'.($this->isTraversable() ? '|\\'.$this->traversableClass : '');
        }

        $array = $this->isHashmap() ? '[string]' : '[]';
        if ($this->isTraversable()) {
            $collectionType = $this->isHashmap() ? ', string' : '';
            $array .= sprintf('|\\%s<%s%s>', $this->traversableClass, $this->subType, $collectionType);
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
     * @return class-string<\Traversable>
     */
    public function getTraversableClass(): string
    {
        if (!$this->isTraversable()) {
            throw new \UnexpectedValueException("Iterable type '{$this}' is not traversable.");
        }

        return $this->traversableClass;
    }

    public function isTraversable(): bool
    {
        return null != $this->traversableClass;
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
        $thisTraversableClass = $this->isTraversable() ? $this->getTraversableClass() : null;

        if ($other instanceof PropertyTypeUnknown) {
            return new self($this->getSubType(), $this->isHashmap(), $nullable, $thisTraversableClass);
        }
        if ($this->isTraversable() && (($other instanceof PropertyTypeClass) && is_a($other->getClassName(), \Traversable::class, true))) {
            return new self($this->getSubType(), $this->isHashmap(), $nullable, $this->findCommonTraversableClass($thisTraversableClass, $other->getClassName()));
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

        $otherTraversableClass = $other->isTraversable() ? $other->getTraversableClass() : null;
        $hashmap = $this->isHashmap() || $other->isHashmap();
        $commonClass = $this->findCommonTraversableClass($thisTraversableClass, $otherTraversableClass);

        if ($other->getSubType() instanceof PropertyTypeUnknown) {
            return new self($this->getSubType(), $hashmap, $nullable, $commonClass);
        }
        if ($this->getSubType() instanceof PropertyTypeUnknown) {
            return new self($other->getSubType(), $hashmap, $nullable, $commonClass);
        }

        return new self($this->getSubType()->merge($other->getSubType()), $hashmap, $nullable, $commonClass);
    }

    /**
     * Find the most derived class that doesn't deny both class hints, meaning the most derived
     * between left and right if one is a child of the other
     */
    private function findCommonTraversableClass(?string $left, ?string $right): ?string
    {
        if (null === $right) {
            return $left;
        }
        if (null === $left) {
            return $right;
        }

        if (is_a($left, $right, true)) {
            return $left;
        }
        if (is_a($right, $left, true)) {
            return $right;
        }

        throw new \UnexpectedValueException("Traversable classes '{$left}' and '{$right}' do not match.");
    }
}
