<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

use Liip\MetadataParser\Exception\InvalidTypeException;

final class PropertyTypeEnum extends AbstractPropertyType
{
    private string $className;
    private ?string $backingType;

    private ?SerializationMode $serializationMode;

    public function __construct(string $className, bool $nullable, ?SerializationMode $serializationMode = null)
    {
        parent::__construct($nullable);
        if (!enum_exists($className)) {
            throw new InvalidTypeException(\sprintf('Given type "%s" is not a PHP 8.1 enum', $className));
        }

        $this->className = $className;
        $this->backingType = null;
        $this->serializationMode = $serializationMode;

        $reflEnum = new \ReflectionEnum($className);
        $backingType = $reflEnum->getBackingType();
        if ($backingType instanceof \ReflectionNamedType) {
            $this->backingType = $backingType->getName();
        }
    }

    public function __toString(): string
    {
        return $this->className.parent::__toString();
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getBackingType(): ?string
    {
        return $this->backingType;
    }

    public function isBackedEnum(): bool
    {
        return null !== $this->backingType;
    }

    public function getSerializationMode(): ?SerializationMode
    {
        return $this->serializationMode;
    }

    public function shouldSerializeAsValue(): bool
    {
        return $this->isBackedEnum() && SerializationMode::Name !== $this->serializationMode;
    }

    public function merge(PropertyType $other): PropertyType
    {
        $nullable = $this->isNullable() && $other->isNullable();

        if ($other instanceof PropertyTypeUnknown) {
            return new self($this->className, $nullable, $this->serializationMode);
        }

        if (!$other instanceof self) {
            throw new \UnexpectedValueException(\sprintf('Can\'t merge type %s with %s, they must be the same or unknown', self::class, \get_class($other)));
        }

        if ($this->getClassName() !== $other->getClassName()) {
            throw new \UnexpectedValueException(\sprintf('Can\'t merge type %s with %s, they must be equal', self::class, \get_class($other)));
        }

        if (null !== $this->serializationMode && null !== $other->getSerializationMode() && $this->serializationMode !== $other->getSerializationMode()) {
            throw new \UnexpectedValueException(\sprintf('Can\'t merge type %s with conflicting serialization modes "%s" and "%s"', self::class, $this->serializationMode->value, $other->getSerializationMode()->value));
        }

        $serializationMode = $this->serializationMode ?? $other->getSerializationMode();

        return new self($this->className, $nullable, $serializationMode);
    }
}
