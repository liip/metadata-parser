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
     * Use if a different format should be used for parsing dates than for generating dates.
     *
     * @var string|null
     */
    private $deserializeFormat;

    public function __construct(?string $format, ?string $zone, ?string $deserializeFormat)
    {
        $this->format = $format;
        $this->zone = $zone;
        $this->deserializeFormat = $deserializeFormat;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function getZone(): ?string
    {
        return $this->zone;
    }

    public function getDeserializeFormat(): ?string
    {
        return $this->deserializeFormat;
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'format' => $this->format,
            'zone' => $this->zone,
            'deserialize_format' => $this->deserializeFormat,
        ]);
    }
}
