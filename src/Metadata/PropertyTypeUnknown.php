<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

final class PropertyTypeUnknown extends AbstractPropertyType
{
    public function __construct(bool $nullable)
    {
        parent::__construct($nullable);
    }

    public function __toString(): string
    {
        return 'mixed';
    }

    public function merge(PropertyType $other): PropertyType
    {
        if (!$other instanceof self) {
            throw new \UnexpectedValueException(sprintf('Can\'t merge type %s with %s, they must be the same', static::class, \get_class($other)));
        }

        return new self($this->isNullable() && $other->isNullable());
    }
}
