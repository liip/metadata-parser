<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser\Model;

use JMS\Serializer\Annotation\UnionDiscriminator;

class ClassUsingUnionDiscriminator
{
    #[UnionDiscriminator(field: 'objectType', map: ['comment' => DiscriminatorComment::class, 'author' => DiscriminatorAuthor::class])]
    public $property;
}
