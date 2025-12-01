<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser\Model;

use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;

class DiscriminatorAuthor
{
    public string $name;

    #[Type(name: 'string')]
    #[SerializedName(name: 'objectType')]
    private $objectType = 'author';
}
