<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser;

use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;

final class ParserContext
{
    /**
     * @var string
     */
    private $root;

    /**
     * @var PropertyVariationMetadata[]
     */
    private $stack = [];

    public function __construct(string $root)
    {
        $this->root = $root;
    }

    public function __toString(): string
    {
        if (0 === \count($this->stack)) {
            return $this->root;
        }

        $stack = array_map(static function (PropertyVariationMetadata $propertyMetadata) {
            return $propertyMetadata->getName();
        }, $this->stack);

        return sprintf('%s->%s', $this->root, implode('->', $stack));
    }

    public function push(PropertyVariationMetadata $property): self
    {
        $context = clone $this;
        $context->stack[] = $property;

        return $context;
    }
}
