<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser\Model;

use JMS\Serializer\Annotation\UnionDiscriminator;

class ClassUsingUnionTyping
{
    #[UnionDiscriminator(field: 'objectType', map: ['comment' => DiscriminatorComment::class, 'author' => DiscriminatorAuthor::class])]
    public DiscriminatorComment|DiscriminatorAuthor $property;
}
