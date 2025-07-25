<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser\NamingStrategy;

final class IdenticalPropertyNamingStrategy implements PropertyNamingStrategyInterface
{
    public function getSerializedName(string $name): string
    {
        return $name;
    }
}
