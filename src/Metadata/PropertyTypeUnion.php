<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

final class PropertyTypeUnion extends AbstractPropertyType
{
    private const DEFAULT_ORDER = 8;

    /**
     * @var array<string, string>
     */
    private array $typeMap = [];

    private ?string $fieldName = null;

    /**
     * @var PropertyType[]
     */
    private array $types;

    /**
     * @param PropertyType[] $types
     */
    public function __construct(array $types, bool $nullable)
    {
        parent::__construct($nullable);

        $this->setTypes($types);
    }

    /**
     * @return PropertyType[]
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @param PropertyType[] $types
     */
    public function setTypes(array $types): void
    {
        $this->types = $this->reorderTypes($types);
    }

    public function getTypeByClassName(string $className): ?PropertyTypeClass
    {
        foreach ($this->types as $type) {
            if (!$type instanceof PropertyTypeClass) {
                continue;
            }

            if ($type->getClassName() === $className) {
                return $type;
            }
        }

        return null;
    }

    public function setTypeMap(array $typeMap): void
    {
        $this->typeMap = $typeMap;
    }

    public function getTypeMap(): array
    {
        return $this->typeMap;
    }

    public function setFieldName(string $fieldName): void
    {
        $this->fieldName = $fieldName;
    }

    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }

    public function __toString(): string
    {
        $classTypes = array_map(
            static fn (PropertyType $type): string => $type->__toString(),
            $this->types
        );

        return implode('|', $classTypes);
    }

    public function merge(PropertyType $other): PropertyType
    {
        if (!$other instanceof self) {
            throw new \UnexpectedValueException(\sprintf('Can\'t merge type %s with %s, they must be the same', self::class, \get_class($other)));
        }

        $mergedTypes = [...$this->getTypes(), ...$other->getTypes()];

        $mergedPropertyType = new self($mergedTypes, $this->isNullable() && $other->isNullable());

        $mergedTypeMap = [...$this->getTypeMap(), ...$other->getTypeMap()];
        $mergedPropertyType->setTypeMap($mergedTypeMap);

        $fieldName = null;
        if (null === $other->getFieldName()) {
            $fieldName = $this->fieldName;
        } elseif (null === $this->getfieldName()) {
            $fieldName = $other->getFieldName();
        } elseif ($other->getFieldName() !== $this->getfieldName()) {
            throw new \UnexpectedValueException(\sprintf('Can\'t merge type union type with field name %s with other union type with field name %s', $this->getFieldName(), $other->getFieldName()));
        }

        $mergedPropertyType->setFieldName($fieldName);

        return $mergedPropertyType;
    }

    /**
     * Sorting the types by primitives first and then by class will make it easier when (de-)serializing the values.
     *
     * @param PropertyType[] $types
     *
     * @return PropertyType[]
     */
    private function reorderTypes(array $types): array
    {
        uasort($types, static function (PropertyType $first, PropertyType $second) {
            $order = ['null' => 0, 'array' => 1, 'true' => 2, 'false' => 3, 'bool' => 4, 'int' => 5, 'float' => 6, 'string' => 7];
            $firstTypeName = $first instanceof PropertyTypeIterable ? 'array' : null;
            $secondTypeName = $second instanceof PropertyTypeIterable ? 'array' : null;
            if ($first instanceof PropertyTypePrimitive) {
                $firstTypeName = $first->getTypeName();
            }

            if ($second instanceof PropertyTypePrimitive) {
                $secondTypeName = $second->getTypeName();
            }

            $firstOrder = $order[$firstTypeName ?? ''] ?? self::DEFAULT_ORDER;
            $secondOrder = $order[$secondTypeName ?? ''] ?? self::DEFAULT_ORDER;

            return $firstOrder <=> $secondOrder;
        });

        return array_values($types);
    }
}
