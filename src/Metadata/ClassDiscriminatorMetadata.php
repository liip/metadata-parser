<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

class ClassDiscriminatorMetadata
{
    /**
     * @var class-string
     */
    public string $baseClass;
    public string $propertyName;
    public string $value;
    public bool $disabled = false;

    /**
     * @var array<string, class-string>
     */
    public array $classMap = [];

    /**
     * @var string[]
     */
    public array $groups = [];

    /**
     * @var array<class-string, ClassMetadata>
     */
    private array $classMetadataList = [];

    /**
     * @param ClassMetadata[] $classMetadataList
     */
    public function setClassMetadataList(array $classMetadataList): void
    {
        $this->classMetadataList = [];
        foreach ($classMetadataList as $classMetadata) {
            $this->addClassMetadata($classMetadata);
        }
    }

    public function getClassMetadataList(): array
    {
        return $this->classMetadataList;
    }

    /**
     * @param class-string $className
     */
    public function getMetadataForClass(string $className): ?ClassMetadata
    {
        return $this->classMetadataList[$className] ?? null;
    }

    private function addClassMetadata(ClassMetadata $classMetadata): void
    {
        $this->classMetadataList[$classMetadata->getClassName()] = $classMetadata;
    }
}
