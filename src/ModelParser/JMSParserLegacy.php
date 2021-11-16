<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser;

use JMS\Serializer\Annotation\ReadOnly;
use JMS\Serializer\Annotation\ReadOnlyProperty;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;

/**
 * Version for PHP 8.0 and older, still supporting the ReadOnly annotation
 */
final class JMSParser extends BaseJMSParser
{
    protected function parsePropertyAnnotationsReadOnly(object $annotation, PropertyVariationMetadata $property): bool
    {
        // ReadOnly is deprecated since JMS serializer 3.14.
        // In older versions, ReadOnlyProperty does not exist and we need to explicitly check for ReadOnly until we mark this library as conflicting with jms serializer < 3.14
        if ($annotation instanceof ReadOnlyProperty
            || $annotation instanceof ReadOnly
        ) {
            $property->setReadOnly($annotation->readOnly);

            return true;
        }

        return false;
    }
}
