<?php

namespace Tests\Liip\MetadataParser\ModelParser\Fixtures;

use Tests\Liip\MetadataParser\ModelParser\ReflectionParserTest;

class TypeDeclarationModel
{
    private string $property1;
    public ?int $property2;
    protected ReflectionParserTest $property3;
}
