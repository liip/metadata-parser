<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

final class PropertyTypeDateTime extends AbstractPropertyType
{
    private const DATE_TIME_TYPES = [
        \DateTime::class,
        \DateTimeImmutable::class,
    ];

    /**
     * @var bool
     */
    private $immutable;

    /**
     * @var DateTimeOptions|null
     */
    private $dateTimeOptions;

    public function __construct(bool $immutable, bool $nullable, DateTimeOptions $dateTimeOptions = null)
    {
        parent::__construct($nullable);
        $this->immutable = $immutable;
        $this->dateTimeOptions = $dateTimeOptions;
    }

    public function __toString(): string
    {
        $class = $this->immutable ? \DateTimeImmutable::class : \DateTime::class;

        return $class.parent::__toString();
    }

    public function isImmutable(): bool
    {
        return $this->immutable;
    }

    public function getFormat(): ?string
    {
        if ($this->dateTimeOptions) {
            return $this->dateTimeOptions->getFormat();
        }

        return null;
    }

    public function getZone(): ?string
    {
        if ($this->dateTimeOptions) {
            return $this->dateTimeOptions->getZone();
        }

        return null;
    }

    public function getDeserializeFormat(): ?string
    {
        if ($this->dateTimeOptions) {
            return $this->dateTimeOptions->getDeserializeFormat();
        }

        return null;
    }

    public function merge(PropertyType $other): PropertyType
    {
        $nullable = $this->isNullable() && $other->isNullable();

        if ($other instanceof PropertyTypeUnknown) {
            return new self($this->immutable, $nullable, $this->dateTimeOptions);
        }
        if (!$other instanceof self) {
            throw new \UnexpectedValueException(sprintf('Can\'t merge type %s with %s, they must be the same or unknown', static::class, \get_class($other)));
        }
        if ($this->isImmutable() !== $other->isImmutable()) {
            throw new \UnexpectedValueException(sprintf('Can\'t merge type %s with %s, they must be equal', static::class, \get_class($other)));
        }

        $options = $this->dateTimeOptions ?: $other->dateTimeOptions;

        return new self($this->immutable, $nullable, $options);
    }

    public static function fromDateTimeClass(string $className, bool $nullable, DateTimeOptions $dateTimeOptions = null): self
    {
        if (!self::isTypeDateTime($className)) {
            throw new \UnexpectedValueException(sprintf('Given type "%s" is not date time class or interface', $className));
        }

        return new self(\DateTimeImmutable::class === $className, $nullable, $dateTimeOptions);
    }

    public static function isTypeDateTime(string $typeName): bool
    {
        return \in_array($typeName, self::DATE_TIME_TYPES, true);
    }
}
