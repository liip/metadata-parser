<?php

declare(strict_types=1);

namespace Liip\MetadataParser;

use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;

final class RawClassMetadataRegistry
{
    /**
     * @var RawClassMetadata[]
     */
    private $classMetadata = [];

    public function add(RawClassMetadata $classMetadata): void
    {
        if ($this->contains($classMetadata->getClassName())) {
            throw new \BadMethodCallException(sprintf('The model for "%s" is already in the registry', $classMetadata->getClassName()));
        }

        $this->classMetadata[$classMetadata->getClassName()] = $classMetadata;
    }

    public function contains(string $className): bool
    {
        return \array_key_exists($className, $this->classMetadata);
    }

    /**
     * @return RawClassMetadata[]
     */
    public function getAll(): array
    {
        return array_values($this->classMetadata);
    }
}
