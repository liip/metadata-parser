<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

/**
 * Options as provided in the JMSSerializer DateTime / DateTimeImmutable type annotations.
 */
final class DateTimeOptions implements \JsonSerializable
{
    /**
     * @var string|null
     */
    private $format;

    /**
     * @var string|null
     */
    private $zone;

    /**
     * Use if different formats should be used for parsing dates than for generating dates.
     *
     * @var string[]|null
     */
    private $deserializeFormats;

    /**
     * @note Passing a string for $deserializeFormats is deprecated, please pass an array instead
     *
     * @param string[]|string|null $deserializeFormats
     */
    public function __construct(?string $format, ?string $zone, $deserializeFormats)
    {
        $this->format = $format;
        $this->zone = $zone;
        $this->deserializeFormats = \is_string($deserializeFormats) ? [$deserializeFormats] : $deserializeFormats;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function getZone(): ?string
    {
        return $this->zone;
    }

    /**
     * @deprecated Please use {@see getDeserializeFormats}
     */
    public function getDeserializeFormat(): ?string
    {
        foreach ($this->deserializeFormats ?? [] as $format) {
            return $format;
        }

        return null;
    }

    public function getDeserializeFormats(): ?array
    {
        return $this->deserializeFormats;
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'format' => $this->format,
            'zone' => $this->zone,
            'deserialize_format' => $this->getDeserializeFormat(),
            'deserialize_formats' => $this->deserializeFormats,
        ]);
    }
}
