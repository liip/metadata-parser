<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

/**
 * Base information about property types.
 *
 * Handles nullable.
 */
abstract class AbstractPropertyType implements PropertyType
{
    /**
     * @var bool
     */
    private $nullable;

    protected function __construct(bool $nullable)
    {
        $this->nullable = $nullable;
    }

    public function __toString(): string
    {
        return $this->isNullable() ? '|null' : '';
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }
}
