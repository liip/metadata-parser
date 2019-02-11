<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;

final class PropertyMetadata extends AbstractPropertyMetadata
{
    /**
     * @var PropertyType
     */
    private $type;

    /**
     * @var string
     */
    private $serializedName;

    /**
     * @param string[] $groups
     */
    public function __construct(
        string $serializedName,
        string $name,
        PropertyType $type = null,
        bool $readOnly = true,
        bool $public = false,
        Version $version = null,
        array $groups = [],
        PropertyAccessor $accessor = null
    ) {
        parent::__construct($name, $readOnly, $public);
        $this->serializedName = $serializedName;
        $this->type = $type ?: new PropertyTypeUnknown(true);
        $this->setVersion($version ?: new Version(null, null));
        $this->setGroups($groups);
        $this->setAccessor($accessor ?: new PropertyAccessor(null, null));
    }

    public function __toString(): string
    {
        return $this->serializedName;
    }

    public static function fromRawProperty(string $serializedName, PropertyVariationMetadata $property): self
    {
        return new self(
            $serializedName,
            $property->getName(),
            $property->getType(),
            $property->isReadOnly(),
            $property->isPublic(),
            $property->getVersion(),
            $property->getGroups(),
            $property->getAccessor()
        );
    }

    public function getType(): PropertyType
    {
        return $this->type;
    }

    public function getSerializedName(): string
    {
        return $this->serializedName;
    }

    public function jsonSerialize(): array
    {
        return array_merge(
            [
                'serialized_name' => $this->serializedName,
            ],
            parent::jsonSerialize(),
            [
                'type' => (string) $this->type,
            ]
        );
    }
}
