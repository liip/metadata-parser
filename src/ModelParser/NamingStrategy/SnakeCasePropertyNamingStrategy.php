<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser\NamingStrategy;

final class SnakeCasePropertyNamingStrategy implements PropertyNamingStrategyInterface
{
    public function getSerializedName(string $name): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_\0', $name));
    }
}
