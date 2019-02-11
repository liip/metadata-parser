<?php

declare(strict_types=1);

namespace Liip\MetadataParser;

use Liip\MetadataParser\Exception\RecursionException;
use Liip\MetadataParser\Metadata\ClassMetadata;
use Liip\MetadataParser\Metadata\PropertyTypeArray;
use Liip\MetadataParser\Metadata\PropertyTypeClass;
use Psr\Log\LoggerInterface;

/**
 * This class walks through the metadata graph and detects cycles.
 *
 * It can be configured to abort the walk at specified paths to work around
 * flawed models. When aborting, it modifies the class metadata in that place,
 * to not have the property.
 * This can be used to avoid recursions that come from a relationship graph
 * that is technically recursive but in reality is not.
 *
 * E.g. product->variants (which themselves are products. You can configure to
 * abort at product->variants->variants to avoid a recursion.
 */
final class RecursionChecker
{
    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @var string[][]
     */
    private $expectedRecursions;

    /**
     * The expected recursions can be absolute, starting with the root class.
     * Or you can specify only a sub path. Both will be detected.
     *
     * A recursion configuration may contain Context::MATCH_EVERYTHING ('*') to
     * match every field at that level.
     *
     * Arrays and hashmaps are specified the same as single fields.
     *
     * @param string[][] $expectedRecursions List of expected recursions
     */
    public function __construct(LoggerInterface $logger = null, array $expectedRecursions = [])
    {
        $this->logger = $logger;
        $this->expectedRecursions = $expectedRecursions;
    }

    /**
     * @throws RecursionException
     */
    public function check(ClassMetadata $classMetadata): ClassMetadata
    {
        return $this->checkClassMetadata($classMetadata, new RecursionContext($classMetadata->getClassName()));
    }

    private function checkClassMetadata(ClassMetadata $classMetadata, RecursionContext $context): ClassMetadata
    {
        $propertiesToRemove = [];

        foreach ($classMetadata->getProperties() as $property) {
            $type = $property->getType();
            if ($type instanceof PropertyTypeArray) {
                $type = $type->getLeafType();
            }

            if ($type instanceof PropertyTypeClass) {
                $propertyClassMetadata = $type->getClassMetadata();
                $propertyContext = $context->push($property);

                foreach ($this->expectedRecursions as $expectedRecursion) {
                    /*
                     * Future feature idea: The expected paths would work just the same if they where not about
                     * recursions but general paths. We could move the check for circuit breaking outside of
                     * the check whether we hit a recursion.
                     */
                    if ($propertyContext->matches($expectedRecursion)) {
                        // Remove property of expected recursion
                        if ($this->logger) {
                            $this->logger->notice(
                                'Expected recursion found for class "{class_name}" in context {context}',
                                [
                                    'class_name' => $propertyClassMetadata->getClassName(),
                                    'context' => (string) $propertyContext,
                                ]
                            );
                        }

                        $propertiesToRemove[] = $property->getName();
                        continue 2;
                    }
                }

                /*
                 * Future feature idea: Instead of 2, we could use a configurable maximum recursion depth.
                 * One could then allow to unroll a recursion up to a certain number and stop it after that.
                 */
                if ($propertyContext->countClassNames($propertyClassMetadata->getClassName()) > 2) {
                    throw RecursionException::forClass($propertyClassMetadata->getClassName(), $propertyContext);
                }

                $type->setClassMetadata($this->checkClassMetadata($propertyClassMetadata, $propertyContext));
            }
        }

        if (0 === \count($propertiesToRemove)) {
            return $classMetadata;
        }

        return $classMetadata->withoutProperties($propertiesToRemove);
    }
}
