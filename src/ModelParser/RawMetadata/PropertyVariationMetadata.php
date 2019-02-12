<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser\RawMetadata;

use Liip\MetadataParser\Metadata\AbstractPropertyMetadata;
use Liip\MetadataParser\Metadata\PropertyAccessor;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeUnknown;
use Liip\MetadataParser\Metadata\VersionRange;

/**
 * A single property variant represents one item of a class that is serialized to a specific name.
 *
 * Different properties or methods with JMS serialized name annotations are considered variants of the same property.
 */
final class PropertyVariationMetadata extends AbstractPropertyMetadata
{
    /**
     * @var PropertyType
     */
    private $type;

    /**
     * @var bool
     */
    private $preferred;

    /**
     * @param string $name Name of the property in PHP or the method name for a virtual property
     */
    public function __construct(string $name, bool $readOnly, bool $public, bool $preferred = false)
    {
        parent::__construct($name, $readOnly, $public);
        $this->type = new PropertyTypeUnknown(true);
        $this->setPreferred($preferred);
    }

    public static function fromReflection(\ReflectionProperty $reflProperty): self
    {
        return new self($reflProperty->getName(), false, $reflProperty->isPublic());
    }

    public function setType(PropertyType $type): void
    {
        $this->type = $type;
    }

    public function getType(): PropertyType
    {
        return $this->type;
    }

    public function setPreferred(bool $preferred): void
    {
        $this->preferred = $preferred;
    }

    public function isPreferred(): bool
    {
        return $this->preferred;
    }

    public function setReadOnly(bool $readOnly): void
    {
        parent::setReadOnly($readOnly);
    }

    public function setPublic(bool $public): void
    {
        parent::setPublic($public);
    }

    public function setGroups(array $groups): void
    {
        parent::setGroups($groups);
    }

    public function setAccessor(PropertyAccessor $accessor): void
    {
        parent::setAccessor($accessor);
    }

    public function setVersionRange(VersionRange $version): void
    {
        parent::setVersionRange($version);
    }

    /**
     * The value can be anything that the consumer understands.
     *
     * However, if it is an object, it should implement JsonSerializable to not
     * break debugging.
     */
    public function setCustomInformation(string $key, $value): void
    {
        parent::setCustomInformation($key, $value);
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['type'] = (string) $this->type;

        return $data;
    }
}
