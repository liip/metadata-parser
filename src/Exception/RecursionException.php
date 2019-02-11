<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Exception;

use Liip\MetadataParser\RecursionContext;

final class RecursionException extends SchemaException
{
    private const FOR_CLASS = 'Recursion found for class "%s" in context %s';

    public static function forClass(string $className, RecursionContext $context): self
    {
        return new self(sprintf(
            self::FOR_CLASS,
            $className,
            (string) $context
        ));
    }
}
