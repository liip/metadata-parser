<?php

declare(strict_types=1);

namespace Liip\MetadataParser;

use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\Metadata\PropertyTypeIterable;
use Liip\MetadataParser\Metadata\PropertyTypeUnion;
use Liip\MetadataParser\ModelParser\ModelParserInterface;
use Liip\MetadataParser\ModelParser\NamingStrategy\PropertyNamingStrategyInterface;
use Liip\MetadataParser\ModelParser\NamingStrategy\SnakeCasePropertyNamingStrategy;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;

final class Parser
{
    /**
     * @var ModelParserInterface[]
     */
    private array $parsers;

    private PropertyNamingStrategyInterface $propertyNamingStrategy;

    /**
     * @param ModelParserInterface[] $parsers
     */
    public function __construct(array $parsers, ?PropertyNamingStrategyInterface $propertyNamingStrategy = null)
    {
        $this->parsers = $parsers;
        $this->propertyNamingStrategy = $propertyNamingStrategy ?? new SnakeCasePropertyNamingStrategy();
    }

    /**
     * @return RawClassMetadata[]
     *
     * @throws ParseException
     */
    public function parse(string $className): array
    {
        $registry = new RawClassMetadataRegistry();

        $this->parseModel($className, $registry);

        return $registry->getAll();
    }

    private function parseModel(string $className, RawClassMetadataRegistry $registry): void
    {
        if ($registry->contains($className)) {
            return;
        }

        $rawClassMetadata = new RawClassMetadata($className);
        foreach ($this->parsers as $parser) {
            $parser->parse($rawClassMetadata, $this->propertyNamingStrategy);
        }
        $registry->add($rawClassMetadata);

        $this->parseDiscriminatorClasses($rawClassMetadata, $registry);

        foreach ($rawClassMetadata->getPropertyVariations() as $property) {
            $type = $property->getType();
            $this->parsePropertyType($type, $registry);
        }
    }

    private function parseDiscriminatorClasses(RawClassMetadata $rawClassMetadata, RawClassMetadataRegistry $registry): void
    {
        if (null === $rawClassMetadata->getDiscriminatorMetadata()) {
            return;
        }

        foreach ($rawClassMetadata->getDiscriminatorMetadata()->classMap as $childClass) {
            $this->parseModel($childClass, $registry);
        }
    }

    private function parsePropertyType(PropertyType $type, RawClassMetadataRegistry $registry): void
    {
        if ($type instanceof PropertyTypeIterable) {
            $type = $type->getLeafType();
        }

        if ($type instanceof PropertyTypeClass) {
            $this->parseModel($type->getClassName(), $registry);
        }

        if ($type instanceof PropertyTypeUnion) {
            foreach ($type->getTypes() as $subType) {
                $this->parsePropertyType($subType, $registry);
            }
        }
    }
}
