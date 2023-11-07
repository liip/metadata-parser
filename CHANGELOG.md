# Changelog

# Version 1.x

# 1.2.0

* DateTimeOptions now features a list of deserialization formats instead of a single string one. Passing a string instead of an array to its `__construct`or is deprecated, and will be forbidden in the next version
  Similarly, `getDeserializeFormat(): ?string` is deprecated in favor of `getDeserializeFormats(): ?array`
* Added `PropertyTypeIterable`, which generalizes `PropertyTypeArray` to allow merging Collection informations like one would with arrays, including between interfaces and concrete classes
* Deprecated `PropertyTypeArray`, please prefer using `PropertyTypeIterable` instead
* `PropertyTypeArray::isCollection()` and `PropertyTypeArray::getCollectionClass()` are deprecated, including in its child classes, in favor of `isTraversable()` and `getTraversableClass()`
* Added a model parser `VisibilityAwarePropertyAccessGuesser` that tries to guess getter and setter methods for non-public properties.

# 1.1.0

* Drop support for PHP 7.2 and PHP 7.3
* Support doctrine annotations `2.x`

# 1.0.0

No changes since 0.6.1.

# 0.6.1

* Do not ignore methods that have no phpdoc but do have a PHP 8.1 attribute to make them virtual properties.

# 0.6.0

* When running with PHP 8, process attributes in addition to the phpdoc annotations.
* Support doctrine collections
* Support `identical` property naming strategy
* Add support for the `MaxDepth` annotation from JMS

# 0.5.0

* Support JMS Serializer `ReadOnlyProperty` in addition to `ReadOnly` to be compatible with serializer 3.14 and newer.
* Support PHP 8.1 (which makes ReadOnly a reserved keyword)

# 0.4.1

* Allow installation with psr/log 2 and 3, to allow installation with Symfony 6

# 0.4.0

* Handle property type declarations in reflection parser.
* [Bugfix] Upgrade array type with `@var Type[]` annotation
* [Bugfix] When extending class redefines a property, use phpdoc from extending class rather than base class
* [Bugfix] Use correct context for relative class names in inherited properties/methods

# 0.3.0

* Support PHP 8, drop support for PHP 7.1
* Support JMS 3 and drop support for JMS 1
* [BC Break] Only if you wrote your own PropertyMetadata class and overwrote `getCustomInformation`: That method now has the return type `:mixed` specified.

# 0.2.1

* [Bugfix] Look in parent classes and traits for imports

# 0.2.0

* [Feature] Support to track custom metadata

# 0.1.0

* Initial release
