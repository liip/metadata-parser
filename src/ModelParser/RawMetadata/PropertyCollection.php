<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser\RawMetadata;

final class PropertyCollection implements \JsonSerializable
{
    /**
     * @var string
     */
    private $serializedName;

    /**
     * @var PropertyVariationMetadata[]
     */
    private $variations = [];

    public function __construct(string $name)
    {
        $this->setSerializedName($name);
    }

    public function __toString(): string
    {
        return $this->serializedName;
    }

    public static function serializedName(string $name): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_\\0', $name));
    }

    /**
     * Try to determine the position of this collection in the supplied order.
     *
     * This goes over the variations and returns the index in $order of the
     * first variation that is found. If no variation is found in $order, null
     * is returned.
     *
     * Note: This strategy will not work when you want a different order for
     * different versions. When the properties/methods that result in the same
     * serialized name have to be ordered differently, we would need a
     * different approach. We would need to keep all properties separately - or
     * keep track of the PHP name and apply reordering after reducing.
     *
     * @param string[] $order Ordered list of property names (PHP code names, not serialized names)
     */
    public function getPosition(array $order): ?int
    {
        foreach ($this->variations as $variation) {
            $pos = \array_search($variation->getName(), $order, true);
            if (false !== $pos) {
                return $pos;
            }
        }

        return null;
    }

    public function setSerializedName(string $name): void
    {
        $this->serializedName = self::serializedName($name);
    }

    public function getSerializedName(): string
    {
        return $this->serializedName;
    }

    public function addVariation(PropertyVariationMetadata $property): void
    {
        $this->variations[] = $property;
    }

    /**
     * @return PropertyVariationMetadata[]
     */
    public function getVariations(): array
    {
        return $this->variations;
    }

    public function hasVariation(string $name): bool
    {
        return null !== $this->findVariation($name);
    }

    /**
     * @param string $name The name of a PropertyVariation is its system name, e.g. for a virtual property the method name
     *
     * @throws \UnexpectedValueException if no variation with this name exists
     */
    public function getVariation(string $name): PropertyVariationMetadata
    {
        $property = $this->findVariation($name);
        if (null === $property) {
            throw new \UnexpectedValueException(sprintf('Property variation %s not found on PropertyCollection %s', $name, $this->serializedName));
        }

        return $property;
    }

    public function removeVariation(string $name): void
    {
        foreach ($this->variations as $i => $property) {
            if ($property->getName() === $name) {
                unset($this->variations[$i]);
                $this->variations = array_values($this->variations);

                break;
            }
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'serialized_name' => $this->serializedName,
            'variations' => $this->variations,
        ];
    }

    private function findVariation(string $name): ?PropertyVariationMetadata
    {
        foreach ($this->variations as $property) {
            if ($property->getName() === $name) {
                return $property;
            }
        }

        return null;
    }
}
