<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser;

/*
 * We need different class definitions for PHP 8.1 and newer, and one for versions older than 8.1.
 * There was a JMS annotation "ReadOnly", but readonly became a reserved keyword in PHP 8.1.
 * For PHP 8.0 and older, we must still support the ReadOnly annotation, even with older versions of JMS serializer where ReadOnly does not yet extend ReadOnlyProperty.
 * For PHP 8.1, our code can not mention ReadOnly. If we do, we get a parse error.
 */

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\Reader;
use JMS\Serializer\Annotation\Accessor;
use JMS\Serializer\Annotation\AccessorOrder;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\PostDeserialize;
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
use JMS\Serializer\Type\Exception\SyntaxError;
use Liip\MetadataParser\Exception\InvalidTypeException;
use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\Metadata\PropertyAccessor;
use Liip\MetadataParser\Metadata\PropertyType;
use Liip\MetadataParser\Metadata\PropertyTypeUnknown;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyCollection;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;
use Liip\MetadataParser\TypeParser\JMSTypeParser;
use Liip\MetadataParser\TypeParser\PhpTypeParser;

/**
 * Parse JMSSerializer annotations.
 *
 * Run this parser *after* the PHPDoc parser as JMS annotations are more precise.
 *
 * @internal
 */
abstract class BaseJMSParser implements ModelParserInterface
{
    private const ACCESS_ORDER_CUSTOM = 'custom';

    /**
     * @var Reader
     */
    private $annotationsReader;

    /**
     * @var PhpTypeParser
     */
    private $phpTypeParser;

    /**
     * @var JMSTypeParser
     */
    protected $jmsTypeParser;

    public function __construct(Reader $annotationsReader)
    {
        $this->annotationsReader = $annotationsReader;
        $this->phpTypeParser = new PhpTypeParser();
        $this->jmsTypeParser = new JMSTypeParser();
    }

    public function parse(RawClassMetadata $classMetadata): void
    {
        try {
            $reflClass = new \ReflectionClass($classMetadata->getClassName());
        } catch (\ReflectionException $e) {
            throw ParseException::classNotFound($classMetadata->getClassName(), $e);
        }

        try {
            $this->parseProperties($reflClass, $classMetadata);
            $this->parseMethods($reflClass, $classMetadata);
            $this->parseClass($reflClass, $classMetadata);
        } catch (SyntaxError $exception) {
            throw new ParseException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    private function parseProperties(\ReflectionClass $reflClass, RawClassMetadata $classMetadata): void
    {
        if ($reflParentClass = $reflClass->getParentClass()) {
            $this->parseProperties($reflParentClass, $classMetadata);
        }

        foreach ($reflClass->getProperties() as $reflProperty) {
            try {
                $annotations = $this->annotationsReader->getPropertyAnnotations($reflProperty);
            } catch (AnnotationException $e) {
                throw ParseException::propertyError((string) $classMetadata, $reflProperty->getName(), $e);
            }

            $property = $this->getProperty($classMetadata, $reflProperty, $annotations);
            $this->parsePropertyAnnotations($classMetadata, $property, $annotations);
        }
    }

    private function parseMethods(\ReflectionClass $reflClass, RawClassMetadata $classMetadata): void
    {
        if ($reflParentClass = $reflClass->getParentClass()) {
            $this->parseMethods($reflParentClass, $classMetadata);
        }

        foreach ($reflClass->getMethods() as $reflMethod) {
            if (false === $reflMethod->getDocComment()) {
                continue;
            }

            try {
                $annotations = $this->annotationsReader->getMethodAnnotations($reflMethod);
            } catch (AnnotationException $e) {
                throw ParseException::propertyError((string) $classMetadata, $reflMethod->getName(), $e);
            }

            if ($this->isVirtualProperty($annotations)) {
                if (!$reflMethod->isPublic()) {
                    throw ParseException::nonPublicMethod((string) $classMetadata, $reflMethod->getName());
                }

                $methodName = $this->getMethodName($annotations, $reflMethod);
                $name = $this->getSerializedName($annotations) ?: $methodName;

                $property = new PropertyVariationMetadata($methodName, true, true);
                $classMetadata->addPropertyVariation($name, $property);

                $property->setType($this->getReturnType($property, $reflMethod, $reflClass));
                $property->setAccessor(new PropertyAccessor($reflMethod->getName(), null));

                $this->parsePropertyAnnotations($classMetadata, $property, $annotations);
            }

            if ($this->isPostDeserializeMethod($annotations)) {
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
            $annotations = $this->gatherClassAnnotations($reflClass);
        } catch (AnnotationException $e) {
            throw ParseException::classError($reflClass->getName(), $e);
        }

        foreach ($annotations as $annotation) {
            switch (true) {
                case $annotation instanceof AccessorOrder:
                    if (self::ACCESS_ORDER_CUSTOM !== $annotation->order) {
                        throw ParseException::unsupportedClassAnnotation((string) $classMetadata, 'AccessorOrder::'.$annotation->order);
                    }

                    // usort is not stable for the same result. we want to preserve order of the fields that are not explicitly mentioned
                    $order = [];
                    $init = \count($annotation->custom);
                    foreach ($classMetadata->getPropertyCollections() as $property) {
                        $position = $property->getPosition($annotation->custom);
                        if (null === $position) {
                            $position = $init++;
                        }
                        $order[$property->getSerializedName()] = $position;
                    }

                    $classMetadata->sortProperties(static function (PropertyCollection $propA, PropertyCollection $propB) use ($order): int {
                        return $order[$propA->getSerializedName()] <=> $order[$propB->getSerializedName()];
                    });
                    break;

                case $annotation instanceof ExclusionPolicy:
                    if (ExclusionPolicy::NONE !== $annotation->policy) {
                        throw ParseException::unsupportedClassAnnotation((string) $classMetadata, 'ExclusionPolicy::'.$annotation->policy);
                    }
                    break;

                case $annotation instanceof XmlRoot:
                    // skip these attributes, we don't do xml
                    break;

                default:
                    if (0 === strncmp('JMS\Serializer\\', \get_class($annotation), \mb_strlen('JMS\Serializer\\'))) {
                        // if there are annotations we can safely ignore, we need to explicitly ignore them
                        throw ParseException::unsupportedClassAnnotation((string) $classMetadata, \get_class($annotation));
                    }
            }
        }
    }

    /**
     * Find the annotations we care about by looking through all ancestors of $reflectionClass.
     *
     * @throws AnnotationException
     *
     * @return object[] Hashmap of annotation class => annotation object
     */
    private function gatherClassAnnotations(\ReflectionClass $reflectionClass): array
    {
        $map = [];
        if ($parent = $reflectionClass->getParentClass()) {
            $map = $this->gatherClassAnnotations($parent);
        }
        $annotations = $this->annotationsReader->getClassAnnotations($reflectionClass);
        foreach ($annotations as $annotation) {
            $map[\get_class($annotation)] = $annotation;
        }

        return $map;
    }

    /**
     * If the annotation is about readonly information, update the $property accordingly and return true.
     *
     * This check is extracted to have different implementations for PHP 8.1 and newer, and for older PHP versions.
     * If the method returns true, we don't try to match the annotation type.
     *
     * @return bool whether $annotation was a readonly information
     */
    abstract protected function parsePropertyAnnotationsReadOnly(object $annotation, PropertyVariationMetadata $property): bool;

    private function parsePropertyAnnotations(RawClassMetadata $classMetadata, PropertyVariationMetadata $property, array $annotations): void
    {
        foreach ($annotations as $annotation) {
            if ($this->parsePropertyAnnotationsReadOnly($annotation, $property)) {
                continue;
            }
            switch (true) {
                case $annotation instanceof Type:
                    if (null === $annotation->name) {
                        throw ParseException::propertyTypeNameNull((string) $classMetadata, (string) $property);
                    }
                    try {
                        $type = $this->jmsTypeParser->parse($annotation->name);
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

                case $annotation instanceof Exclude:
                    if (null !== $annotation->if) {
                        throw ParseException::unsupportedPropertyAnnotation((string) $classMetadata, (string) $property, 'Exclude::if');
                    }
                    $classMetadata->removePropertyVariation((string) $property);
                    break;

                case $annotation instanceof Groups:
                    $property->setGroups($annotation->groups);
                    break;

                case $annotation instanceof Accessor:
                    $property->setAccessor(new PropertyAccessor($annotation->getter, $annotation->setter));
                    break;

                case $annotation instanceof Since:
                    $property->setVersionRange($property->getVersionRange()->withSince($annotation->version));
                    break;

                case $annotation instanceof Until:
                    $property->setVersionRange($property->getVersionRange()->withUntil($annotation->version));
                    break;

                case $annotation instanceof VirtualProperty:
                    // we handle this separately
                case $annotation instanceof SerializedName:
                    // we handle this separately
                case $annotation instanceof XmlAttribute:
                case $annotation instanceof XmlKeyValuePairs:
                case $annotation instanceof XmlList:
                case $annotation instanceof XmlMap:
                case $annotation instanceof XmlValue:
                    // skip these attributes, we don't do xml
                    break;

                default:
                    if (0 === strncmp('JMS\Serializer\\', \get_class($annotation), \mb_strlen('JMS\Serializer\\'))) {
                        // if there are annotations we can safely ignore, we need to explicitly ignore them
                        throw ParseException::unsupportedPropertyAnnotation((string) $classMetadata, (string) $property, \get_class($annotation));
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
    private function getProperty(RawClassMetadata $classMetadata, \ReflectionProperty $reflProperty, array $annotations): PropertyVariationMetadata
    {
        $defaultName = PropertyCollection::serializedName($reflProperty->getName());
        $name = $this->getSerializedName($annotations) ?: $defaultName;
        if ($classMetadata->hasPropertyVariation($reflProperty->getName())) {
            $property = $classMetadata->getPropertyVariation($reflProperty->getName());
            if ($defaultName !== $name && $classMetadata->hasPropertyCollection($defaultName)) {
                $classMetadata->renameProperty($defaultName, $name);
            }
        } else {
            $property = PropertyVariationMetadata::fromReflection($reflProperty);
            $classMetadata->addPropertyVariation($name, $property);
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

    private function getSerializedName(array $annotations): ?string
    {
        foreach ($annotations as $annotation) {
            if ($annotation instanceof SerializedName) {
                return $annotation->name;
            }
        }

        return null;
    }

    private function isVirtualProperty(array $annotations): bool
    {
        foreach ($annotations as $annotation) {
            if ($annotation instanceof VirtualProperty) {
                return true;
            }
        }

        return false;
    }

    private function isPostDeserializeMethod(array $annotations): bool
    {
        foreach ($annotations as $annotation) {
            if ($annotation instanceof PostDeserialize) {
                return true;
            }
        }

        return false;
    }

    private function getMethodName(array $annotations, \ReflectionMethod $reflMethod): string
    {
        $name = $reflMethod->getName();
        foreach ($annotations as $annotation) {
            if ($annotation instanceof VirtualProperty && null !== $annotation->name) {
                $name = $annotation->name;
                break;
            }
        }

        if (0 === strpos($name, 'get')) {
            $name = lcfirst(substr($name, 3));
        }

        return $name;
    }
}

if (PHP_VERSION_ID > 80100) {
    require 'JMSParser81.php';
} else {
    require 'JMSParserLegacy.php';
}
