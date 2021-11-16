# Changelog

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
