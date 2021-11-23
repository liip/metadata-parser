<?php

namespace Tests\Liip\MetadataParser\ModelParser\Fixtures;

use Liip\MetadataParser\ModelParser\ReflectionParser;
use Tests\Liip\MetadataParser\ModelParser\ReflectionParserTest;

class UnionTypeDeclarationModel
{
    protected ReflectionParserTest|ReflectionParser $property1;
    public int|string|null $property2;
}
