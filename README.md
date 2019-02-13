# Liip Metadata Parser

**This project is Open Sourced based on work that we did initially as closed source at Liip, it may be lacking some documentation. If there is anything that you need or have questions about we would love to see you open an issue! :)**

This is a parser for building model metadata from PHP classes. The metadata model can
then be used to generate code or configuration. For example a serializer or
ElasticSearch schema for types.

The metadata is geared toward this use case. PHP level constructs that
represent the same information are grouped together: Methods for virtual
properties and fields that have the same serialized name, but are valid in
different versions.

This extensible parser can process PHP code and annotations or other metadata.
You could write your own parsers, but this library comes with support for:

* Reflection
* PhpDoc
* JMSSerializer annotations

## Contributing

If you want to contribute to the project (awesome!!), please read the
[Contributing Guidelines](https://github.com/liip/metadata-parser/blob/master/CONTRIBUTING.md)
and adhere to our [Code Of Conduct](https://github.com/liip/metadata-parser/blob/master/CODE_OF_CONDUCT.md)

## Where do I go for help?
If you need, open an issue.

## Setup
```php
use Doctrine\Common\Annotations\AnnotationReader;
use Liip\MetadataParser\Builder;
use Liip\MetadataParser\Parser;
use Liip\MetadataParser\RecursionChecker;
use Liip\MetadataParser\ModelParser\JMSParser;
use Liip\MetadataParser\ModelParser\LiipMetadataAnnotationParser;
use Liip\MetadataParser\ModelParser\PhpDocParser;
use Liip\MetadataParser\ModelParser\ReflectionParser;

$parser = new Parser(
    new ReflectionParser(),
    new PhpDocParser(),
    new JMSParser(new AnnotationReader()),
    new LiipMetadataAnnotationParser(new AnnotationReader()),
);

$recursionChecker = new RecursionChecker(new NullLogger());

$builder = new Builder($parser, $recursionChecker);
```

## Usage

The `Builder::build` method is the main entry point to get a `ClassMetadata`
object. The builder accepts an array of `Reducer` to select which property to
use when there are several options. A reducer can lead to drop a property if
none of the variants is acceptable. Multiple options for a single field mainly
come from the `@SerializedName` and `@VirtualProperty` annotations of
JMSSerializer:

* GroupReducer: Select the property based on whether it is in any of the
  specified groups;
* VersionReducer: Select the property based on whether it is included in the
  specified version;
* TakeBestReducer: Make sure that we end up with the property that has the same
  name as the serialized name, if we still have multiple options after the
  other reducers.

```php
use Liip\MetadataParser\Reducer\GroupReducer;
use Liip\MetadataParser\Reducer\PreferredReducer;
use Liip\MetadataParser\Reducer\TakeBestReducer;
use Liip\MetadataParser\Reducer\VersionReducer;

$reducers = [
    new VersionReducer('2'),
    new GroupReducer(['api', 'detail']),
    new PreferredReducer(),
    new TakeBestReducer(),
];
$metadata = $builder->build(MyClass::class, $reducers);
```

The `ClassMetadata` provides all information on a class. Properties have a
`PropertyType` to tell what kind of property they are. Properties that hold
another class, are of the type `PropertyTypeClass` that has the method
`getClassMetadata()` to get the metadata of the nested class. This structure
is validated to not contain any infinite recursion.

### Handling Edge Cases with @Preferred

This library provides its own annotation in `Liip\MetadataParser\Annotation\Preferred`
to specify which property to use in case there are several options. This can be
useful for example when serializing models without specifying a version, when
they use different virtual properties in different versions.

```php
use JMS\Serializer\Annotation as JMS;
use Liip\MetadataParser\Annotation as Liip;

class Product
{
    /**
     * @JMS\Since("2")
     * @JMS\Type("string")
     */
    public $name;
    
    /**
     * @JMS\Until("1")
     * @JMS\SerializedName("name")
     * @JMS\Type("string")
     * @Liip\Preferred
     */
    public $legacyName;
}
```

### Expected Recursion: Working with Flawed Models

The RecursionChecker accepts a second parameter to specify places where to
break recursion. This is useful if your model tree looks like it has recursions
but actually does not have them. JMSSerializer always acts on the actual data
and therefore does not notice a recursion as long as it is not infinite.

For example, lets say you have a `Product` that has a field `variants` which is
again list of `Product`. Those variant products use the same class and
therefore have the `variants` field. However, in real data a variant never
contains further variants. To avoid a recursion exception for this example, you
would specify:

```php
$expectedRecursions = [
    ['variants', 'variants'],
];
$recursionChecker = new RecursionChecker(new NullLogger(), $expectedRecursions);
``` 

With this configuration, the `ClassMetadata` found in the property type for the
variants property of the final model will have no field `variants`, so that
code working on the metadata does not need to worry about infinite recursion.

## Extending the metadata parser

This library comes with a couple of parsers, but you can write your own to
handle custom information specific to your project. Use the
`PropertyVariationMetadata::setCustomInformation` method to add custom data,
and use `PropertyMetadata::getCustomInformation` to read it in your metadata
consumers.
