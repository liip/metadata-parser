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

    /**
     * @deprecated Please prefer {@link getDeserializeFormats}
     */
    public function getDeserializeFormat(): ?string
    {
        if ($this->dateTimeOptions) {
            return $this->dateTimeOptions->getDeserializeFormat();
        }

        return null;
    }

    public function getDeserializeFormats(): ?array
    {
        if ($this->dateTimeOptions) {
            return $this->dateTimeOptions->getDeserializeFormats();
        }

        return null;
    }

    public function merge(PropertyType $other): PropertyType
    {
        $nullable = $this->isNullable() && $other->isNullable();

        if ($other instanceof PropertyTypeUnknown) {
            return new self($this->immutable, $nullable, $this->dateTimeOptions);
        }
        if (($other instanceof PropertyTypeClass) && is_a($other->getClassName(), \DateTimeInterface::class, true)) {
            if (is_a($other->getClassName(), \DateTimeImmutable::class, true)) {
                return new self(true, $nullable, $this->dateTimeOptions);
            }
            if (is_a($other->getClassName(), \DateTime::class, true) || (\DateTimeInterface::class === $other->getClassName())) {
                return new self(false, $nullable, $this->dateTimeOptions);
            }

            throw new \UnexpectedValueException("Can't merge type '{$this}' with '{$other}', they must be the same or unknown");
        }
        if (!$other instanceof self) {
            throw new \UnexpectedValueException("Can't merge type '{$this}' with '{$other}', they must be the same or unknown");
        }
        if ($this->isImmutable() !== $other->isImmutable()) {
            throw new \UnexpectedValueException("Can't merge type '{$this}' with '{$other}', they must be equal");
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

    /**
     * Find the most derived class that doesn't deny both class hints, meaning the most derived
     * between left and right if one is a child of the other
     */
    protected function findCommonDateTimeClass(?string $left, ?string $right): ?string
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
