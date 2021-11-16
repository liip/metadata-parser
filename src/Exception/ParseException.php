<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Exception;

final class ParseException extends SchemaException
{
    private const CLASS_NOT_FOUND = 'Class or interface "%s" could not be found, maybe it\'s not autoloadable?';
    private const CLASS_ERROR = 'Class "%s" couldn\'t be parsed because of an error: %s';
    private const PROPERTY_ERROR = 'Property "%s::%s" couldn\'t be parsed because of an error: %s';
    private const PROPERTY_TYPE_ERROR = 'Property "%s::%s" has an invalid type which results in: %s';
    private const PROPERTY_TYPE_NAME_NULL = 'Property "%s::%s" has an invalid type annotation. [Type Error] Attribute "name" of @JMS\Type may not be null.';
    private const PROPERTY_TYPE_CONFLICT = 'Property "%s::%s" has different type definitions which conflict: %s != %s';
    private const UNSUPPORTED_CLASS_ANNOTATION = 'Class "%s" has an unsupported annotation "%s"';
    private const UNSUPPORTED_PROPERTY_ANNOTATION = 'Property "%s::%s" has an unsupported annotation "%s"';
    private const NON_PUBLIC_METHOD = 'Method "%s::%s" is not public and therefore cannot be included';

    private const PROPERTY_ALREADY_EXISTS = 'Property "%s" is already defined for "%s", cannot add it twice';
    private const CLASS_NOT_PARSED = 'Class "%s" of property "%s::%s" was not parsed, something went wrong';

    public static function classNotFound(string $className, \Exception $previousException): self
    {
        return new self(
            sprintf(self::CLASS_NOT_FOUND, $className),
            $previousException->getCode(),
            $previousException
        );
    }

    public static function classError(string $className, \Exception $previousException): self
    {
        return new self(
            sprintf(self::CLASS_ERROR, $className, $previousException->getMessage()),
            $previousException->getCode(),
            $previousException
        );
    }

    public static function propertyError(string $className, string $propertyName, \Exception $previousException): self
    {
        return new self(
            sprintf(self::PROPERTY_ERROR, $className, $propertyName, $previousException->getMessage()),
            $previousException->getCode(),
            $previousException
        );
    }

    public static function propertyTypeError(string $className, string $propertyName, \Exception $previousException): self
    {
        return new self(
            sprintf(self::PROPERTY_TYPE_ERROR, $className, $propertyName, $previousException->getMessage()),
            $previousException->getCode(),
            $previousException
        );
    }

    public static function propertyTypeNameNull(string $className, string $propertyName): self
    {
        return new self(sprintf(self::PROPERTY_TYPE_NAME_NULL, $className, $propertyName));
    }

    public static function propertyTypeConflict(string $className, string $propertyName, string $typeA, string $typeB, \Exception $previousException): self
    {
        return new self(
            sprintf(self::PROPERTY_TYPE_CONFLICT, $className, $propertyName, $typeA, $typeB),
            $previousException->getCode(),
            $previousException
        );
    }

    public static function unsupportedClassAnnotation(string $className, string $annotation): self
    {
        return new self(sprintf(self::UNSUPPORTED_CLASS_ANNOTATION, $className, $annotation));
    }

    public static function unsupportedPropertyAnnotation(string $className, string $propertyName, string $annotation): self
    {
        return new self(sprintf(self::UNSUPPORTED_PROPERTY_ANNOTATION, $className, $propertyName, $annotation));
    }

    public static function nonPublicMethod(string $className, string $methodName): self
    {
        return new self(sprintf(self::NON_PUBLIC_METHOD, $className, $methodName));
    }

    public static function propertyAlreadyExists(string $propertyName, string $className): self
    {
        return new self(sprintf(self::PROPERTY_ALREADY_EXISTS, $propertyName, $className));
    }

    public static function classNotParsed(string $notFoundClassName, string $className, string $propertyName): self
    {
        return new self(sprintf(self::CLASS_NOT_PARSED, $notFoundClassName, $className, $propertyName));
    }
}
