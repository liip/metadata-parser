<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Annotation;

/**
 * Used to select which property to use in cases where there is ambiguity.
 *
 * For example, when serializing models that have different properties for
 * different versions with JMS serializer, and not specifying any version.
 *
 * @Annotation
 * @Target({"METHOD", "PROPERTY"})
 */
final class Preferred
{
}
