<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

/**
 * A model to track versioning information of a property.
 */
final class Version implements \JsonSerializable
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

    public function getSince(): ?string
    {
        return $this->since;
    }

    public function setSince(string $since): void
    {
        $this->since = $since;
    }

    public function setUntil(string $until): void
    {
        $this->until = $until;
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
