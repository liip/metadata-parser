<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser\Fixtures;

use Liip\MetadataParser\ModelParser\ReflectionParser;
use Tests\Liip\MetadataParser\ModelParser\ReflectionParserTest;

class IntersectionTypeDeclarationModel
{
    protected ReflectionParserTest&ReflectionParser $property1;
}
