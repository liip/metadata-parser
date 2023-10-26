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
    private $collectionClass;

    /**
     * @param class-string<\Traversable>|null $collectionClass
     */
    public function __construct(PropertyType $subType, bool $hashmap, bool $nullable, string $collectionClass = null)
    {
        parent::__construct($subType, $hashmap, $nullable, null != $collectionClass);

        $this->collectionClass = $collectionClass;
    }

    public function __toString(): string
    {
        if ($this->subType instanceof PropertyTypeUnknown) {
            return 'array'.($this->isCollection() ? '|\\'.$this->collectionClass : '');
        }

        $array = $this->isHashmap() ? '[string]' : '[]';
        if ($this->isCollection()) {
            $collectionType = $this->isHashmap() ? ', string' : '';
            $array .= sprintf('|\\%s<%s%s>', $this->collectionClass, $this->subType, $collectionType);
        }

        return ((string) $this->subType).$array.AbstractPropertyType::__toString();
    }

    public function getCollectionClass(): ?string
    {
        return $this->collectionClass;
    }

    public function isCollection(): bool
    {
        return null != $this->getCollectionClass();
    }

    public function merge(PropertyType $other): PropertyType
    {
        $nullable = $this->isNullable() && $other->isNullable();

        if ($other instanceof PropertyTypeUnknown) {
            return new self($this->subType, $this->isHashmap(), $nullable, $this->getCollectionClass());
        }
        if ($this->isCollection() && (($other instanceof PropertyTypeClass) && is_a($other->getClassName(), Collection::class, true))) {
            return new self($this->getSubType(), $this->isHashmap(), $nullable, $this->findCommonCollectionClass($this->getCollectionClass(), $other->getClassName()));
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

        if ($other->isCollection()) {
            $otherCollectionClass = ($other instanceof self) ? $other->getCollectionClass() : Collection::class;
        } else {
            $otherCollectionClass = null;
        }
        $hashmap = $this->isHashmap() || $other->isHashmap();
        $commonClass = $this->findCommonCollectionClass($this->getCollectionClass(), $otherCollectionClass);

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
    protected function findCommonCollectionClass(?string $left, ?string $right): ?string
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

        throw new \UnexpectedValueException("Collection classes '{$left}' and '{$right}' do not match.");
    }
}
