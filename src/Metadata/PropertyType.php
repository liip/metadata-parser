<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

/**
 * This is a marker interface for property types.
 */
interface PropertyType
{
    /**
     * Information about the type suitable for debugging.
     */
    public function __toString(): string;

    /**
     * Whether this property can be nullified.
     *
     * This information is available even for unknown properties.
     */
    public function isNullable(): bool;

    /**
     * Merges another property type into this one.
     *
     * @throws \UnexpectedValueException if the types are not compatible
     */
    public function merge(self $other): self;
}
