# Schema Parser

A parser for building model metadata from PHP classes. The metadata model can
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

## Setup
```php
use Doctrine\Common\Annotations\AnnotationReader;
use Migros\Infrastructure\Schema\Builder;
use Migros\Infrastructure\Schema\Parser;
use Migros\Infrastructure\Schema\RecursionChecker;
use Migros\Infrastructure\Schema\ModelParser\JMSParser;
use Migros\Infrastructure\Schema\ModelParser\PhpDocParser;
use Migros\Infrastructure\Schema\ModelParser\ReflectionParser;

$parser = new Parser(
    new ReflectionParser(),
    new PhpDocParser(),
    new JMSParser(new AnnotationReader()),
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
use Migros\Infrastructure\Schema\Reducer\GroupReducer;
use Migros\Infrastructure\Schema\Reducer\TakeBestReducer;
use Migros\Infrastructure\Schema\Reducer\VersionReducer;

$reducers = [
    new VersionReducer('2'),
    new GroupReducer(['api', 'detail']),
    new TakeBestReducer(),
];
$metadata = $builder->build(MyClass::class, $reducers);
```

The `ClassMetadata` provides all information on a class. Properties have a
`PropertyType` to tell what kind of property they are. Properties that hold
another class, are of the type `PropertyTypeClass` that has the method
`getClassMetadata()` to get the metadata of the nested class. This structure
is validated to not contain any infinite recursion.

## Expected Recursion: Working with Flawed Models

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
