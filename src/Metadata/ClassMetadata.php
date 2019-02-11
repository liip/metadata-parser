<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;

final class ClassMetadata implements \JsonSerializable
{
    /**
     * @var string
     */
    private $className;

    /**
     * This list contains all properties of the data model of this class, including virtual properties
     * (e.g. from JMS serializer).
     *
     * The reducer has selected the variant of the property to use here, in case of serialized name clashes.
     *
     * @var PropertyMetadata[]
     */
    private $properties = [];

    /**
     * Method names to call on the class after it has been deserialized.
     *
     * This is rather specific, but for now is good enough.
     *
     * @var string[]
     */
    private $postDeserializeMethods = [];

    /**
     * @var ParameterMetadata[]
     */
    private $constructorParameters = [];

    /**
     * @param PropertyMetadata[]  $properties
     * @param ParameterMetadata[] $constructorParameters
     * @param string[]            $postDeserializeMethods
     */
    public function __construct(string $className, array $properties, array $constructorParameters = [], array $postDeserializeMethods = [])
    {
        \assert(array_reduce($constructorParameters, static function (bool $carry, $parameter): bool {
            return $carry && $parameter instanceof ParameterMetadata;
        }, true));

        $this->className = $className;
        $this->constructorParameters = $constructorParameters;
        $this->postDeserializeMethods = $postDeserializeMethods;

        foreach ($properties as $property) {
            $this->addProperty($property);
        }
    }

    public function __toString(): string
    {
        return $this->className;
    }

    /**
     * @param PropertyMetadata[] $properties
     */
    public static function fromRawClassMetadata(RawClassMetadata $rawClassMetadata, array $properties): self
    {
        return new self(
            $rawClassMetadata->getClassName(),
            $properties,
            $rawClassMetadata->getConstructorParameters(),
            $rawClassMetadata->getPostDeserializeMethods()
        );
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @return PropertyMetadata[]
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @return string[]
     */
    public function getPostDeserializeMethods(): array
    {
        return $this->postDeserializeMethods;
    }

    /**
     * @return ParameterMetadata[]
     */
    public function getConstructorParameters(): array
    {
        return $this->constructorParameters;
    }

    public function hasConstructorParameter(string $name): bool
    {
        foreach ($this->constructorParameters as $parameter) {
            if ($parameter->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    public function getConstructorParameter(string $name): ParameterMetadata
    {
        foreach ($this->constructorParameters as $parameter) {
            if ($parameter->getName() === $name) {
                return $parameter;
            }
        }

        throw new \InvalidArgumentException(sprintf('Class %s has no constructor parameter called "%s"', $this->className, $name));
    }

    /**
     * Returns a copy of the class metadata with the specified properties removed.
     *
     * This can be used for expected recursions to remove the affected properties.
     *
     * @param string[] $propertyNames
     */
    public function withoutProperties(array $propertyNames): self
    {
        $properties = array_values(array_filter(
            $this->properties,
            static function (PropertyMetadata $property) use ($propertyNames): bool {
                return !\in_array($property->getName(), $propertyNames, true);
            }
        ));

        return new self(
            $this->className,
            $properties,
            $this->constructorParameters,
            $this->postDeserializeMethods
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'class_name' => $this->className,
            'properties' => $this->properties,
            'post_deserialize_method' => $this->postDeserializeMethods,
            'constructor_parameters' => $this->constructorParameters,
        ]);
    }

    /**
     * @throws ParseException if the property already exists
     */
    private function addProperty(PropertyMetadata $property): void
    {
        foreach ($this->properties as $prop) {
            if ($prop->getSerializedName() === $property->getSerializedName()) {
                throw ParseException::propertyAlreadyExists((string) $property, (string) $this);
            }
        }

        $this->properties[] = $property;
    }
}
