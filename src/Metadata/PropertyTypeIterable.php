<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

use Doctrine\Common\Collections\Collection;

class PropertyTypeIterable extends AbstractPropertyType
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
    private $collectionClass;

    /**
     * @param PropertyType $subType
     * @param bool $hashmap
     * @param bool $nullable
     * @param class-string<iterable>|null $collectionClass
     */
    public function __construct(PropertyType $subType, bool $hashmap, bool $nullable, ?string $collectionClass = null)
    {
        parent::__construct($nullable);

        $this->subType = $subType;
        $this->hashmap = $hashmap;
        $this->collectionClass = $collectionClass;
    }

    /**
     * @internal This only exists as a bridge to deprecated class PropertyTypeArray
     * @deprecated Please remove this and just directly construct a PropertyTypeIterable
     * @return static
     */
    protected function create(PropertyType $subType, bool $hashmap, bool $nullable, ?string $collectionClass = null)
    {
        if (static::class === PropertyTypeArray::class) {
            $self = new static($subType, $hashmap, $nullable, !empty($collectionClass));
            $self->collectionClass = $collectionClass;
            return $self;
        }

        return new static($subType, $hashmap, $nullable, $collectionClass);
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

    public function getCollectionClass(): ?string
    {
        return $this->collectionClass;
    }

    public function isCollection(): bool
    {
        return null != $this->getCollectionClass();
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
            return new self($this->getSubType(), $this->isHashmap(), $nullable, $this->findCommonCollectionClass($this->collectionClass, $other->getClassName()));
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
        $commonClass = $this->findCommonCollectionClass($this->collectionClass, $other->collectionClass);

        if ($other->getSubType() instanceof PropertyTypeUnknown) {
            return new self($this->getSubType(), $hashmap, $nullable, $commonClass);
        }
        if ($this->getSubType() instanceof PropertyTypeUnknown) {
            return new self($other->getSubType(), $hashmap, $nullable, $commonClass);
        }

        return new self($this->getSubType()->merge($other->getSubType()), $hashmap, $nullable, $commonClass);
    }

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
