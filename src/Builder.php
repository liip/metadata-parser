<?php

declare(strict_types=1);

namespace Liip\MetadataParser;

use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\Metadata\ClassMetadata;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeArray;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Liip\MetadataParser\Reducer\PropertyReducerInterface;

/**
 * Builder for class metadata.
 *
 * Future feature idea: In addition to the build method, this builder could
 * also provide a whole hashmap of class name => metadata. It should then not
 * set the metadata on the property types. This would allow to work with
 * general model graphs that may include recursion.
 */
final class Builder
{
    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var RecursionChecker
     */
    private $recursionChecker;

    public function __construct(Parser $parser, RecursionChecker $recursionChecker)
    {
        $this->parser = $parser;
        $this->recursionChecker = $recursionChecker;
    }

    /**
     * Build the tree for the specified class.
     *
     * This tree is guaranteed to be a tree and can not be a graph with recursion.
     *
     * @param PropertyReducerInterface[] $reducers
     *
     * @throws ParseException
     */
    public function build(string $className, array $reducers = []): ClassMetadata
    {
        $rawClassMetadataList = $this->parser->parse($className);

        /** @var ClassMetadata[] $classMetadataList */
        $classMetadataList = [];
        foreach ($rawClassMetadataList as $rawClassMetadata) {
            $classMetadataList[$rawClassMetadata->getClassName()] = PropertyReducer::reduce($rawClassMetadata, $reducers);
        }

        foreach ($classMetadataList as $classMetadata) {
            foreach ($classMetadata->getProperties() as $property) {
                try {
                    $this->setTypeClassMetadata($property->getType(), $classMetadataList);
                } catch (\UnexpectedValueException $e) {
                    throw ParseException::classNotParsed($e->getMessage(), (string) $classMetadata, (string) $property);
                }
            }
        }

        return $this->recursionChecker->check($classMetadataList[$className]);
    }

    /**
     * @param ClassMetadata[] $classMetadataList
     *
     * @throws \UnexpectedValueException if the class is not found
     */
    private function setTypeClassMetadata(PropertyType $type, array $classMetadataList): void
    {
        if ($type instanceof PropertyTypeClass) {
            if (!\array_key_exists($type->getClassName(), $classMetadataList)) {
                throw new \UnexpectedValueException($type->getClassName());
            }
            $type->setClassMetadata($classMetadataList[$type->getClassName()]);

            return;
        }

        if ($type instanceof PropertyTypeArray) {
            $this->setTypeClassMetadata($type->getLeafType(), $classMetadataList);
        }
    }
}
