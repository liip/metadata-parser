<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser\Model;

use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;

class DiscriminatorComment
{
    public string $text;

    #[Type(name: 'string')]
    #[SerializedName(name: 'objectType')]
    private $objectType = 'comment';
}
