<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser;

use JMS\Serializer\Annotation\ReadOnlyProperty;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;

/**
 * Version for PHP 8.1+ without the ReadOnly annotation.
 */
final class JMSParser extends BaseJMSParser
{
    protected function parsePropertyAnnotationsReadOnly(object $annotation, PropertyVariationMetadata $property): bool
    {
        if ($annotation instanceof ReadOnlyProperty) {
            $property->setReadOnly($annotation->readOnly);

            return true;
        }

        return false;
    }
}
