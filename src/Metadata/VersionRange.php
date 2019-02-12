<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

/**
 * A model to track versioning information of a property.
 */
final class VersionRange implements \JsonSerializable
{
    /**
     * @var string|null
     */
    private $since;

    /**
     * @var string|null
     */
    private $until;

    public function __construct(?string $since, ?string $until)
    {
        $this->since = $since;
        $this->until = $until;
    }

    public static function all(): self
    {
        return new self(null, null);
    }

    public function isDefined(): bool
    {
        return null !== $this->since || null !== $this->until;
    }

    /**
     * Check if this version range allows a lower version than the other version range.
     *
     * Returns false if both have the same lower bound.
     */
    public function allowsLowerThan(self $other): bool
    {
        if (null === $other->since) {
            return false;
        }
        if (null === $this->since) {
            return true;
        }

        return \version_compare($this->since, $other->since, '<');
    }

    /**
     * Check if this version range allows a higher version than the other version range.
     *
     * Returns false if both have the same upper bound.
     */
    public function allowsHigherThan(self $other)
    {
        if (null === $this->until) {
            return false;
        }
        if (null === $other->until) {
            return true;
        }

        return \version_compare($this->until, $other->until, '>');
    }

    /**
     * The lowest allowed version or `null` when there is no lower bound.
     */
    public function getSince(): ?string
    {
        return $this->since;
    }

    /**
     * The highest allowed version or `null` when there is no upper bound.
     */
    public function getUntil(): ?string
    {
        return $this->until;
    }

    public function withSince(string $since): self
    {
        $copy = clone $this;
        $copy->since = $since;

        return $copy;
    }

    public function withUntil(string $until): self
    {
        $copy = clone $this;
        $copy->until = $until;

        return $copy;
    }

    public function isIncluded(string $version): bool
    {
        if (null !== $this->since && version_compare($version, $this->since, '<')) {
            return false;
        }
        if (null !== $this->until && version_compare($version, $this->until, '>')) {
            return false;
        }

        return true;
    }

    public function jsonSerialize(): array
    {
        return \array_filter([
            'since' => $this->since,
            'until' => $this->until,
        ]);
    }
}
