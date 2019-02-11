<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Exception;

final class InvalidTypeException extends SchemaException
{
    private const CLASS_NOT_FOUND = 'Class or interface "%s" could not be found, maybe it\'s not autoloadable?';

    public static function classNotFound(string $className, \Exception $previousException = null): self
    {
        return new self(
            sprintf(self::CLASS_NOT_FOUND, $className),
            $previousException ? $previousException->getCode() : 0,
            $previousException
        );
    }
}
