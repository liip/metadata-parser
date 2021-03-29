<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

final class PropertyTypePrimitive extends AbstractPropertyType
{
    private const TYPE_MAP = [
        'boolean' => 'bool',
        'integer' => 'int',
        'double' => 'float',
        'real' => 'float',
    ];

    private const PRIMITIVE_TYPES = [
        'string',
        'int',
        'float',
        'bool',
    ];

    /**
     * @var string|null
     */
    private $typeName;

    public function __construct(string $typeName, bool $nullable)
    {
        parent::__construct($nullable);
        if (\array_key_exists($typeName, self::TYPE_MAP)) {
            $typeName = self::TYPE_MAP[$typeName];
        }
        if (!self::isTypePrimitive($typeName)) {
            throw new \UnexpectedValueException(sprintf('Given type "%s" is not primitive', $typeName));
        }
        $this->typeName = $typeName;
    }

    public function __toString(): string
    {
        return $this->typeName.parent::__toString();
    }

    public function getTypeName(): string
    {
        return $this->typeName;
    }

    public function merge(PropertyType $other): PropertyType
    {
        $nullable = $this->isNullable() && $other->isNullable();

        if ($other instanceof PropertyTypeUnknown) {
            return new self($this->typeName, $nullable);
        }
        if (!$other instanceof self) {
            throw new \UnexpectedValueException(sprintf('Can\'t merge type %s with %s, they must be the same or unknown', static::class, \get_class($other)));
        }
        if ($this->getTypeName() !== $other->getTypeName()) {
            throw new \UnexpectedValueException(sprintf('Can\'t merge type %s with %s, they must be equal', static::class, \get_class($other)));
        }

        return new self($this->typeName, $nullable);
    }

    public static function isTypePrimitive(string $typeName): bool
    {
        if (\array_key_exists($typeName, self::TYPE_MAP)) {
            $typeName = self::TYPE_MAP[$typeName];
        }

        return \in_array($typeName, self::PRIMITIVE_TYPES, true);
    }
}
