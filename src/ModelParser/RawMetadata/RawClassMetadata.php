<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser\RawMetadata;

use Liip\MetadataParser\Metadata\ParameterMetadata;

/**
 * Parse time model for the class meta data.
 *
 * The parser will then reduce this to a ClassMetadata for consumers of the schema parser.
 */
final class RawClassMetadata implements \JsonSerializable
{
    /**
     * @var string
     */
    private $className;

    /**
     * This list contains the property collections for each property.
     *
     * The grouping happens based on the serialized name and several properties could have the same serialized name or
     * methods with annotations can lead to serialized name clashes. The PropertyReducer is responsible for converting
     * this into a single PropertyMetadata.
     *
     * @var PropertyCollection[]
     */
    private $properties = [];

    /**
     * @var string[]
     */
    private $postDeserializeMethods = [];

    /**
     * @var ParameterMetadata[]
     */
    private $constructorParameters = [];

    public function __construct(string $className)
    {
        $this->className = $className;
    }

    public function __toString(): string
    {
        return $this->className;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function hasPropertyVariation(string $name): bool
    {
        return null !== $this->findPropertyVariation($name);
    }

    /**
     * @throws \UnexpectedValueException if no variation with this name exists
     */
    public function getPropertyVariation(string $name): PropertyVariationMetadata
    {
        $property = $this->findPropertyVariation($name);
        if (null === $property) {
            throw new \UnexpectedValueException(sprintf('Property variation %s not found on class %s', $name, $this->className));
        }

        return $property;
    }

    /**
     * @return PropertyVariationMetadata[]
     */
    public function getPropertyVariations(): iterable
    {
        foreach ($this->properties as $prop) {
            yield from $prop->getVariations();
        }
    }

    public function addPropertyVariation(string $serializedName, PropertyVariationMetadata $property): void
    {
        if ($this->hasPropertyCollection($serializedName)) {
            $prop = $this->getPropertyCollection($serializedName);
        } else {
            $prop = new PropertyCollection($serializedName);
            $this->addPropertyCollection($prop);
        }

        $prop->addVariation($property);
    }

    /**
     * Renames an already registered property.
     *
     * This is a separate step because the initial serialized name is guessed from the property name but other parser
     * steps can detect that the name should be different.
     *
     * @throws \UnexpectedValueException if the property cannot be found
     */
    public function renameProperty(string $propertyName, string $serializedName): void
    {
        if (!$this->hasPropertyCollection($propertyName)) {
            throw new \UnexpectedValueException(sprintf('Property "%s::%s" not found to rename', (string) $this, $propertyName));
        }
        $prop = $this->getPropertyCollection($propertyName);

        if ($this->hasPropertyCollection($serializedName)) {
            $target = $this->getPropertyCollection($serializedName);
            if ($target === $prop) {
                throw new \LogicException(sprintf('You can not rename %s into %s as it is the same property. Did you miss to handle camelCase properties with PropertyCollection::serializedName?', $propertyName, $serializedName));
            }
            foreach ($target->getVariations() as $variation) {
                $prop->addVariation($variation);
            }

            $key = array_search($target, $this->properties, true);
            if (false === $key) {
                throw new \RuntimeException(sprintf('This should not be possible: Target property %s found but then not found $this->properties. While renaming from %s::%s', $serializedName, $this->getClassName(), $propertyName));
            }
            unset($this->properties[$key]);
        }

        $prop->setSerializedName($serializedName);
    }

    /**
     * Removes an already registered property from the metadata.
     *
     * This can be used for example for JMS Exclude annotation.
     *
     * @param string $name Name used in PropertyVariationMetadata::__construct
     */
    public function removePropertyVariation(string $name): void
    {
        foreach ($this->properties as $i => $property) {
            if ($property->hasVariation($name)) {
                $property->removeVariation($name);
                if (0 === \count($property->getVariations())) {
                    unset($this->properties[$i]);
                    $this->properties = array_values($this->properties);
                }

                break;
            }
        }
    }

    /**
     * Get the property collections with their variants for all properties of this class.
     *
     * @return PropertyCollection[]
     */
    public function getPropertyCollections(): array
    {
        return $this->properties;
    }

    public function hasPropertyCollection(string $serializedName): bool
    {
        return null !== $this->findPropertyCollection($serializedName);
    }

    /**
     * Get a property collection of variants for a single property.
     *
     * @throws \UnexpectedValueException if no collection with this name exists
     */
    public function getPropertyCollection(string $serializedName): PropertyCollection
    {
        $property = $this->findPropertyCollection($serializedName);
        if (null === $property) {
            throw new \UnexpectedValueException(sprintf('Property collection %s not found on class %s', $serializedName, $this->className));
        }

        return $property;
    }

    /**
     * @throws \UnexpectedValueException if the property already exists
     */
    public function addPropertyCollection(PropertyCollection $property): void
    {
        if ($this->hasPropertyCollection($property->getSerializedName())) {
            throw new \UnexpectedValueException(sprintf('Property "%s" is already defined on model %s, cannot add it twice', (string) $property, (string) $this));
        }

        $this->properties[] = $property;
    }

    /**
     * Usort the properties with this callable.
     */
    public function sortProperties(callable $f): void
    {
        usort($this->properties, $f);
    }

    public function addPostDeserializeMethod(string $method): void
    {
        $this->postDeserializeMethods[] = $method;
    }

    /**
     * @return string[]
     */
    public function getPostDeserializeMethods(): array
    {
        return $this->postDeserializeMethods;
    }

    public function addConstructorParameter(ParameterMetadata $parameter): void
    {
        $this->constructorParameters[] = $parameter;
    }

    /**
     * @return ParameterMetadata[]
     */
    public function getConstructorParameters(): array
    {
        return $this->constructorParameters;
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

    private function findPropertyCollection(string $name): ?PropertyCollection
    {
        $serializedName = PropertyCollection::serializedName($name);
        foreach ($this->properties as $property) {
            if ($property->getSerializedName() === $serializedName) {
                return $property;
            }
        }

        return null;
    }

    private function findPropertyVariation(string $name): ?PropertyVariationMetadata
    {
        foreach ($this->properties as $prop) {
            if ($prop->hasVariation($name)) {
                return $prop->getVariation($name);
            }
        }

        return null;
    }
}
