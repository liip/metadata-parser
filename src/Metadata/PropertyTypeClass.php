<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

use Liip\MetadataParser\Exception\InvalidTypeException;

final class PropertyTypeClass extends AbstractPropertyType
{
    /**
     * @var string
     */
    private $className;

    /**
     * @var ClassMetadata|null
     */
    private $classMetadata;

    public function __construct(string $className, bool $nullable)
    {
        parent::__construct($nullable);
        if (!self::isTypeCustomClass($className)) {
            throw new InvalidTypeException(sprintf('Given type "%s" is not a custom class or interface but another supported type', $className));
        }
        if (!class_exists($className) && !interface_exists($className)) {
            throw InvalidTypeException::classNotFound($className);
        }

        $this->className = $className;
    }

    public function __toString(): string
    {
        return $this->className.parent::__toString();
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getClassMetadata(): ClassMetadata
    {
        if (null === $this->classMetadata) {
            throw new \BadMethodCallException('Internal error, custom class property type is missing the metadata. Looks like the schema builder didn\'t set it, which is a bug');
        }

        return $this->classMetadata;
    }

    /**
     * This method is only to be used by the parsing process and is required to avoid a chicken-and-egg problem.
     *
     * @internal
     */
    public function setClassMetadata(ClassMetadata $classMetadata): void
    {
        $this->classMetadata = $classMetadata;
    }

    public function merge(PropertyType $other): PropertyType
    {
        $nullable = $this->isNullable() && $other->isNullable();

        if ($other instanceof PropertyTypeUnknown) {
            return new self($this->className, $nullable);
        }
        if (!$other instanceof self) {
            throw new \UnexpectedValueException(sprintf('Can\'t merge type %s with %s, they must be the same or unknown', static::class, \get_class($other)));
        }
        if ($this->getClassName() !== $other->getClassName()) {
            throw new \UnexpectedValueException(sprintf('Can\'t merge type %s with %s, they must be equal', static::class, \get_class($other)));
        }

        return new self($this->className, $nullable);
    }

    public static function isTypeCustomClass(string $typeName): bool
    {
        return !PropertyTypePrimitive::isTypePrimitive($typeName)
            && !PropertyTypeDateTime::isTypeDateTime($typeName);
    }
}
