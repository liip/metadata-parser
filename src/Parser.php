<?php

declare(strict_types=1);

namespace Liip\MetadataParser;

use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\Metadata\PropertyTypeArray;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\ModelParser\ModelParserInterface;
use Liip\MetadataParser\ModelParser\ParserContext;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;

final class Parser
{
    /**
     * @var ModelParserInterface[]
     */
    private $parsers;

    /**
     * @param ModelParserInterface[] $parsers
     */
    public function __construct(array $parsers)
    {
        $this->parsers = $parsers;
    }

    /**
     * @throws ParseException
     *
     * @return RawClassMetadata[]
     */
    public function parse(string $className): array
    {
        $registry = new RawClassMetadataRegistry();

        $this->parseModel($className, new ParserContext($className), $registry);

        return $registry->getAll();
    }

    private function parseModel(string $className, ParserContext $context, RawClassMetadataRegistry $registry): void
    {
        if ($registry->contains($className)) {
            return;
        }

        $rawClassMetadata = new RawClassMetadata($className);
        foreach ($this->parsers as $parser) {
            $parser->parse($rawClassMetadata);
        }
        $registry->add($rawClassMetadata);

        foreach ($rawClassMetadata->getPropertyVariations() as $property) {
            $type = $property->getType();
            if ($type instanceof PropertyTypeArray) {
                $type = $type->getLeafType();
            }
            if ($type instanceof PropertyTypeClass) {
                $this->parseModel($type->getClassName(), $context->push($property), $registry);
            }
        }
    }
}
