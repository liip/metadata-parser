<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

enum SerializationMode: string
{
    case Value = 'value';
    case Name = 'name';
}
