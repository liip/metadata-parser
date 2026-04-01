<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser\Model;

use JMS\Serializer\Annotation\Type;
use Tests\Liip\MetadataParser\ModelParser\Fixtures\DirectionEnum;
use Tests\Liip\MetadataParser\ModelParser\Fixtures\SuitEnum;

class ClassWithEnums
{
    #[Type('enum<'.SuitEnum::class.'>')]
    public SuitEnum $suit;

    #[Type('enum<'.SuitEnum::class.", 'name'>")]
    public SuitEnum $suitWithName;

    #[Type('enum')]
    public SuitEnum $suitWithoutType;

    public DirectionEnum $direction;
}
