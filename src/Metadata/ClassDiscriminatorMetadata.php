<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

class ClassDiscriminatorMetadata
{
    public string $baseClass;
    public string $propertyName;
    public string $value;
    public bool $disabled = false;

    /**
     * @var string[]
     */
    public array $classMap = [];

    /**
     * @var string[]
     */
    public array $groups = [];

    /**
     * @var ClassMetadata[]
     */
    private array $classMetadataList = [];

    /**
     * @param ClassMetadata[] $classMetadataList
     */
    public function setClassMetadataList(array $classMetadataList): void
    {
        $this->classMetadataList = [];
        foreach ($classMetadataList as $classMetadata) {
            if (!$classMetadata instanceof ClassMetadata) {
                throw new \InvalidArgumentException(sprintf('Expected instance of %s', ClassMetadata::class));
            }

            $this->classMetadataList[$classMetadata->getClassName()] = $classMetadata;
        }
    }

    public function getClassMetadataList(): array
    {
        return $this->classMetadataList;
    }

    public function getMetadataForClass(string $className): ?ClassMetadata
    {
        return $this->classMetadataList[$className] ?? null;
    }
}
