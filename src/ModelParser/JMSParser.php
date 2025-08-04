<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser;

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\Reader;
use JMS\Serializer\Annotation\Accessor;
use JMS\Serializer\Annotation\AccessorOrder;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\MaxDepth;
use JMS\Serializer\Annotation\PostDeserialize;
use JMS\Serializer\Annotation\ReadOnlyProperty;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\Until;
use JMS\Serializer\Annotation\VirtualProperty;
use JMS\Serializer\Annotation\XmlAttribute;
use JMS\Serializer\Annotation\XmlKeyValuePairs;
use JMS\Serializer\Annotation\XmlList;
use JMS\Serializer\Annotation\XmlMap;
use JMS\Serializer\Annotation\XmlRoot;
use JMS\Serializer\Annotation\XmlValue;
use JMS\Serializer\Metadata\Driver\AttributeDriver\AttributeReader;
use JMS\Serializer\Type\Exception\SyntaxError;
use Liip\MetadataParser\Exception\InvalidTypeException;
use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\Metadata\PropertyAccessor;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeUnknown;
use Liip\MetadataParser\ModelParser\NamingStrategy\PropertyNamingStrategyInterface;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyCollection;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;
use Liip\MetadataParser\TypeParser\JMSTypeParser;
use Liip\MetadataParser\TypeParser\PhpTypeParser;

/**
 * Parse JMSSerializer attributes/annotations.
 *
 * Run this parser *after* the PHPDoc parser as JMS attributes are more precise.
 *
 * @internal
 */
final class JMSParser implements ModelParserInterface
{
    private const ACCESS_ORDER_CUSTOM = 'custom';

    private PhpTypeParser $phpTypeParser;

    private JMSTypeParser $jmsTypeParser;

    private Reader $annotationOrAttributeReader;

    public function __construct(Reader $reader)
    {
        $this->annotationOrAttributeReader = new AttributeReader($reader);

        $this->phpTypeParser = new PhpTypeParser();
        $this->jmsTypeParser = new JMSTypeParser();
    }

    public function parse(RawClassMetadata $classMetadata, PropertyNamingStrategyInterface $propertyNamingStrategy): void
    {
        try {
            $reflClass = new \ReflectionClass($classMetadata->getClassName());
        } catch (\ReflectionException $e) {
            throw ParseException::classNotFound($classMetadata->getClassName(), $e);
        }

        try {
            $this->parseProperties($reflClass, $classMetadata, $propertyNamingStrategy);
            $this->parseMethods($reflClass, $classMetadata);
            $this->parseClass($reflClass, $classMetadata);
        } catch (SyntaxError $exception) {
            throw new ParseException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    private function parseProperties(\ReflectionClass $reflClass, RawClassMetadata $classMetadata, PropertyNamingStrategyInterface $propertyNamingStrategy): void
    {
        if ($reflParentClass = $reflClass->getParentClass()) {
            $this->parseProperties($reflParentClass, $classMetadata, $propertyNamingStrategy);
        }

        foreach ($reflClass->getProperties() as $reflProperty) {
            try {
                $attributes = $this->annotationOrAttributeReader->getPropertyAnnotations($reflProperty);
            } catch (AnnotationException $e) {
                throw ParseException::propertyError((string) $classMetadata, $reflProperty->getName(), $e);
            }

            $property = $this->getProperty($classMetadata, $reflProperty, $attributes, $propertyNamingStrategy);
            $this->parsePropertyAttributes($classMetadata, $property, $attributes);
        }
    }

    private function parseMethods(\ReflectionClass $reflClass, RawClassMetadata $classMetadata): void
    {
        if ($reflParentClass = $reflClass->getParentClass()) {
            $this->parseMethods($reflParentClass, $classMetadata);
        }

        foreach ($reflClass->getMethods() as $reflMethod) {
            try {
                $attributes = $this->annotationOrAttributeReader->getMethodAnnotations($reflMethod);
            } catch (AnnotationException $e) {
                throw ParseException::propertyError((string) $classMetadata, $reflMethod->getName(), $e);
            }
            if ($this->isVirtualProperty($attributes)) {
                if (!$reflMethod->isPublic()) {
                    throw ParseException::nonPublicMethod((string) $classMetadata, $reflMethod->getName());
                }

                $methodName = $this->getMethodName($attributes, $reflMethod);
                $name = $this->getSerializedName($attributes) ?: $methodName;

                $property = new PropertyVariationMetadata($methodName, true, true);
                $classMetadata->addPropertyVariation($name, $property);

                $property->setType($this->getReturnType($property, $reflMethod, $reflClass));
                $property->setAccessor(new PropertyAccessor($reflMethod->getName(), null));

                $this->parsePropertyAttributes($classMetadata, $property, $attributes);
            }

            if ($this->isPostDeserializeMethod($attributes)) {
                if (!$reflMethod->isPublic()) {
                    throw ParseException::nonPublicMethod((string) $classMetadata, $reflMethod->getName());
                }

                $classMetadata->addPostDeserializeMethod($reflMethod->getName());
            }
        }
    }

    private function parseClass(\ReflectionClass $reflClass, RawClassMetadata $classMetadata): void
    {
        try {
            $attributes = $this->gatherClassAttributes($reflClass);
        } catch (AnnotationException $e) {
            throw ParseException::classError($reflClass->getName(), $e);
        }
        foreach ($attributes as $attribute) {
            switch (true) {
                case $attribute instanceof AccessorOrder:
                    if (self::ACCESS_ORDER_CUSTOM !== $attribute->order) {
                        throw ParseException::unsupportedClassAttribute((string) $classMetadata, 'AccessorOrder::'.$attribute->order);
                    }

                    // usort is not stable for the same result. we want to preserve order of the fields that are not explicitly mentioned
                    $order = [];
                    $init = \count($attribute->custom);
                    foreach ($classMetadata->getPropertyCollections() as $property) {
                        $position = $property->getPosition($attribute->custom);
                        if (null === $position) {
                            $position = $init++;
                        }
                        $order[$property->getSerializedName()] = $position;
                    }

                    $classMetadata->sortProperties(static function (PropertyCollection $propA, PropertyCollection $propB) use ($order): int {
                        return $order[$propA->getSerializedName()] <=> $order[$propB->getSerializedName()];
                    });
                    break;

                case $attribute instanceof ExclusionPolicy:
                    if (ExclusionPolicy::NONE !== $attribute->policy) {
                        throw ParseException::unsupportedClassAttribute((string) $classMetadata, 'ExclusionPolicy::'.$attribute->policy);
                    }
                    break;

                case $attribute instanceof XmlRoot:
                    // skip these attributes, we don't do xml
                    break;

                default:
                    if (0 === strncmp('JMS\Serializer\\', \get_class($attribute), mb_strlen('JMS\Serializer\\'))) {
                        // if there are attributes we can safely ignore, we need to explicitly ignore them
                        throw ParseException::unsupportedClassAttribute((string) $classMetadata, \get_class($attribute));
                    }
            }
        }
    }

    /**
     * Find the attributes we care about by looking through all ancestors of $reflectionClass.
     *
     * @return object[] Hashmap of attribute class => attribute object
     */
    private function gatherClassAttributes(\ReflectionClass $reflectionClass): array
    {
        $map = [];
        if ($parent = $reflectionClass->getParentClass()) {
            $map = $this->gatherClassAttributes($parent);
        }

        $attributes = $this->annotationOrAttributeReader->getClassAnnotations($reflectionClass);
        foreach ($attributes as $attribute) {
            $map[\get_class($attribute)] = $attribute;
        }

        return $map;
    }

    private function parsePropertyAttributes(RawClassMetadata $classMetadata, PropertyVariationMetadata $property, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            switch (true) {
                case $attribute instanceof Type:
                    if (null === $attribute->name) {
                        throw ParseException::propertyTypeNameNull((string) $classMetadata, (string) $property);
                    }
                    try {
                        $type = $this->jmsTypeParser->parse($attribute->name);
                    } catch (InvalidTypeException $e) {
                        throw ParseException::propertyTypeError((string) $classMetadata, (string) $property, $e);
                    }

                    if ($property->getType() instanceof PropertyTypeUnknown) {
                        $property->setType($type);
                    } else {
                        try {
                            $property->setType($property->getType()->merge($type));
                        } catch (\UnexpectedValueException $e) {
                            throw ParseException::propertyTypeConflict((string) $classMetadata, (string) $property, (string) $property->getType(), (string) $type, $e);
                        }
                    }
                    break;

                case $attribute instanceof Exclude:
                    if (null !== $attribute->if) {
                        throw ParseException::unsupportedPropertyAttribute((string) $classMetadata, (string) $property, 'Exclude::if');
                    }
                    $classMetadata->removePropertyVariation((string) $property);
                    break;

                case $attribute instanceof Groups:
                    $property->setGroups($attribute->groups);
                    break;

                case $attribute instanceof Accessor:
                    $property->setAccessor(new PropertyAccessor($attribute->getter, $attribute->setter));
                    break;

                case $attribute instanceof Since:
                    $property->setVersionRange($property->getVersionRange()->withSince($attribute->version));
                    break;

                case $attribute instanceof Until:
                    $property->setVersionRange($property->getVersionRange()->withUntil($attribute->version));
                    break;

                case $attribute instanceof ReadOnlyProperty:
                    $property->setReadOnly(true);
                    break;

                case $attribute instanceof MaxDepth:
                    $property->setMaxDepth($attribute->depth);
                    break;

                case $attribute instanceof VirtualProperty:
                    // we handle this separately
                case $attribute instanceof SerializedName:
                    // we handle this separately
                case $attribute instanceof XmlAttribute:
                case $attribute instanceof XmlKeyValuePairs:
                case $attribute instanceof XmlList:
                case $attribute instanceof XmlMap:
                case $attribute instanceof XmlValue:
                    // skip these attributes, we don't do xml
                    break;

                default:
                    if (0 === strncmp('JMS\Serializer\\', \get_class($attribute), mb_strlen('JMS\Serializer\\'))) {
                        // if there are attributes we can safely ignore, we need to explicitly ignore them
                        throw ParseException::unsupportedPropertyAttribute((string) $classMetadata, (string) $property, \get_class($attribute));
                    }
                    break;
            }
        }
    }

    /**
     * Returns the property metadata for the specified property.
     *
     * If the property already exists on the class metadata this is returned.
     * If the property has a serialized name that overrides the name of an existing property, it will be renamed and merged.
     */
    private function getProperty(RawClassMetadata $classMetadata, \ReflectionProperty $reflProperty, array $attributes, PropertyNamingStrategyInterface $propertyNamingStrategy): PropertyVariationMetadata
    {
        $defaultName = $propertyNamingStrategy->getSerializedName($reflProperty->getName());
        $serializedName = $this->getSerializedName($attributes) ?: $defaultName;
        if ($classMetadata->hasPropertyVariation($reflProperty->getName())) {
            $property = $classMetadata->getPropertyVariation($reflProperty->getName());
            if ($defaultName !== $serializedName && $classMetadata->hasPropertyCollection($defaultName)) {
                $classMetadata->renameProperty($reflProperty->getName(), $serializedName);
            }
        } else {
            $property = PropertyVariationMetadata::fromReflection($reflProperty);
            $classMetadata->addPropertyVariation($serializedName, $property);
        }

        return $property;
    }

    private function getReturnType(PropertyVariationMetadata $property, \ReflectionMethod $reflMethod, \ReflectionClass $reflClass): PropertyType
    {
        $type = new PropertyTypeUnknown(true);

        $reflType = $reflMethod->getReturnType();
        if (null !== $reflType) {
            $type = $this->phpTypeParser->parseReflectionType($reflType);
        }

        try {
            $docBlockType = $this->getReturnTypeOfMethod($reflMethod);
        } catch (InvalidTypeException $e) {
            throw ParseException::propertyTypeError($reflClass->getName(), (string) $property, $e);
        }

        if (null === $docBlockType) {
            return $type;
        }

        try {
            return $type->merge($docBlockType);
        } catch (\UnexpectedValueException $e) {
            throw ParseException::propertyTypeConflict($reflClass->getName(), (string) $property, (string) $type, (string) $docBlockType, $e);
        }
    }

    private function getReturnTypeOfMethod(\ReflectionMethod $reflMethod): ?PropertyType
    {
        $docComment = $reflMethod->getDocComment();
        if (false === $docComment) {
            return null;
        }

        foreach (explode("\n", $docComment) as $line) {
            if (1 === preg_match('/@return ([^ ]+)/', $line, $matches)) {
                return $this->phpTypeParser->parseAnnotationType($matches[1], $reflMethod->getDeclaringClass());
            }
        }

        return null;
    }

    private function getSerializedName(array $attributes): ?string
    {
        foreach ($attributes as $attribute) {
            if ($attribute instanceof SerializedName) {
                return $attribute->name;
            }
        }

        return null;
    }

    private function isVirtualProperty(array $attributes): bool
    {
        foreach ($attributes as $attribute) {
            if ($attribute instanceof VirtualProperty) {
                return true;
            }
        }

        return false;
    }

    private function isPostDeserializeMethod(array $attributes): bool
    {
        foreach ($attributes as $attribute) {
            if ($attribute instanceof PostDeserialize) {
                return true;
            }
        }

        return false;
    }

    private function getMethodName(array $attributes, \ReflectionMethod $reflMethod): string
    {
        $name = $reflMethod->getName();
        foreach ($attributes as $attribute) {
            if ($attribute instanceof VirtualProperty && null !== $attribute->name) {
                $name = $attribute->name;
                break;
            }
        }

        if (0 === strpos($name, 'get')) {
            $name = lcfirst(substr($name, 3));
        }

        return $name;
    }
}
