<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

use Doctrine\Common\Collections\Collection;

/**
 * This property type can be merged with PropertyTypeClass<T>, provided that T is, inherits from, or is a parent class of $this->collectionClass
 * This property type can be merged with PropertyTypeIterable, if :
 *  - we're not merging a plain array PropertyTypeIterable into a hashmap one,
 *  - and the collection classes of each are either not present on both sides, or are the same, or parent-child of one another
 */
final class PropertyTypeIterable extends PropertyTypeArray
{
    /**
     * @var string
     */
    private $traversableClass;

    /**
     * @param class-string<\Traversable>|null $traversableClass
     */
    public function __construct(PropertyType $subType, bool $hashmap, bool $nullable, string $traversableClass = null)
    {
        parent::__construct($subType, $hashmap, $nullable, null != $traversableClass);

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

        return ((string) $this->subType).$array.AbstractPropertyType::__toString();
    }

    /**
     * @deprecated Please prefer using {@link getTraversableClass}
     *
     * @return class-string<Collection>|null
     */
    public function getCollectionClass(): ?string
    {
        return $this->isCollection() ? null : $this->traversableClass;
    }

    /**
     * @deprecated Please prefer using {@link isTraversable}
     */
    public function isCollection(): bool
    {
        return (null != $this->traversableClass) && is_a($this->traversableClass, Collection::class, true);
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

    public function merge(PropertyType $other): PropertyType
    {
        $nullable = $this->isNullable() && $other->isNullable();
        $thisTraversableClass = $this->isTraversable() ? $this->getTraversableClass() : null;

        if ($other instanceof PropertyTypeUnknown) {
            return new self($this->subType, $this->isHashmap(), $nullable, $thisTraversableClass);
        }
        if ($this->isTraversable() && (($other instanceof PropertyTypeClass) && is_a($other->getClassName(), \Traversable::class, true))) {
            return new self($this->getSubType(), $this->isHashmap(), $nullable, $this->findCommonCollectionClass($thisTraversableClass, $other->getClassName()));
        }
        if (!$other instanceof parent) {
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
        $commonClass = $this->findCommonCollectionClass($thisTraversableClass, $otherTraversableClass);

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
    private function findCommonCollectionClass(?string $left, ?string $right): ?string
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
