<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

/**
 * A single property represents one item of a class that is serialized to a specific name.
 */
abstract class AbstractPropertyMetadata implements \JsonSerializable
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $readOnly;

    /**
     * @var bool
     */
    private $public;

    /**
     * @var string[]
     */
    private $groups = [];

    /**
     * @var PropertyAccessor
     */
    private $accessor;

    /**
     * @var VersionRange
     */
    private $versionRange;

    /**
     * @param string $name Name of the property in PHP or the method name for a virtual property
     */
    public function __construct(string $name, bool $readOnly, bool $public)
    {
        $this->name = $name;
        $this->readOnly = $readOnly;
        $this->public = $public;
        $this->accessor = PropertyAccessor::none();
        $this->versionRange = VersionRange::all();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    /**
     * @return string[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getAccessor(): PropertyAccessor
    {
        return $this->accessor;
    }

    public function getVersionRange(): VersionRange
    {
        return $this->versionRange;
    }

    public function jsonSerialize(): array
    {
        $data = [
            'name' => $this->name,
            'is_public' => $this->public,
            'is_read_only' => $this->readOnly,
        ];

        if (\count($this->groups) > 0) {
            $data['groups'] = $this->groups;
        }
        if ($this->accessor->isDefined()) {
            $data['accessor'] = $this->accessor;
        }
        if ($this->versionRange->isDefined()) {
            $data['version'] = $this->versionRange;
        }

        return $data;
    }

    abstract public function getType(): PropertyType;

    protected function setVersionRange(VersionRange $version): void
    {
        $this->versionRange = $version;
    }

    protected function setAccessor(PropertyAccessor $accessor): void
    {
        $this->accessor = $accessor;
    }

    /**
     * @param string[] $groups
     */
    protected function setGroups(array $groups): void
    {
        $this->groups = $groups;
    }

    protected function setPublic(bool $public): void
    {
        $this->public = $public;
    }
}
