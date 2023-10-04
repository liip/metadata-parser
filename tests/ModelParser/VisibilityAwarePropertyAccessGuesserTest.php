<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser;

use Generator;
use Liip\MetadataParser\ModelParser\ModelParserInterface;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;
use Liip\MetadataParser\ModelParser\ReflectionParser;
use Liip\MetadataParser\ModelParser\VisibilityAwarePropertyAccessGuesser;
use PHPUnit\Framework\TestCase;

class VisibilityAwarePropertyAccessGuesserTest extends TestCase
{
    /**
     * @dataProvider provideClassesTests
     */
    public function testSimpleClasses($class, array $parsers, int $expectedPropertyCount, ?array $accessType): void
    {
        $classMetadata = $this->parseClass($class, $parsers);

        $this->assertSame(\get_class($class), $classMetadata->getClassName());
        $this->assertCount($expectedPropertyCount, $classMetadata->getPropertyCollections(), 'Number of properties should match');

        if (null !== $accessType) {
            ['public' => $public, 'hasGetter' => $hasGetter, 'hasSetter' => $hasSetter] = $accessType;

            foreach ($classMetadata->getPropertyVariations() as $propertyVariation) {
                $this->assertSame($public, $propertyVariation->isPublic());

                if (null !== $hasGetter) {
                    $this->assertSame($hasGetter, $propertyVariation->getAccessor()->hasGetterMethod());
                }
                if (null !== $hasSetter) {
                    $this->assertSame($hasSetter, $propertyVariation->getAccessor()->hasSetterMethod());
                }
            }
        }
    }

    /**
     * @param class-string|object    $class
     * @param ModelParserInterface[] $parsers
     */
    public function parseClass($class, array $parsers): RawClassMetadata
    {
        $class = \is_object($class) ? \get_class($class) : $class;
        $classMetadata = new RawClassMetadata($class);

        foreach ($parsers as $parser) {
            $parser->parse($classMetadata);
        }

        return $classMetadata;
    }

    /**
     * @return Generator<array{
     *  'class': object,
     *  'parsers': ModelParserInterface[],
     *  'expectedPropertyCount': int,
     *  'accessType': null|array{
     *     'public': bool,
     *     'hasGetter': null|bool,
     *     'hasSetter': null|bool,
     *  },
     * }>
     */
    public static function provideClassesTests(): \Generator
    {
        yield 'NoPredecessor' => [
            'class' => new class() {
                public ?string $name = 'php';
            },
            'parsers' => [
                new VisibilityAwarePropertyAccessGuesser(),
            ],
            'expectedPropertyCount' => 0,
            'accessType' => null,
        ];
        yield 'Empty' => [
            'class' => new class() {
            },
            'parsers' => [
                new ReflectionParser(),
                new VisibilityAwarePropertyAccessGuesser(),
            ],
            'expectedPropertyCount' => 0,
            'accessType' => null,
        ];
        yield 'SinglePublic' => [
            'class' => new class() {
                public ?string $name = 'php';
            },
            'parsers' => [
                new ReflectionParser(),
                new VisibilityAwarePropertyAccessGuesser(),
            ],
            'expectedPropertyCount' => 1,
            'accessType' => [
                'public' => true,
                'hasGetter' => null,
                'hasSetter' => null,
            ],
        ];
        yield 'SinglePrivate' => [
            'class' => new class() {
                private ?string $name = 'php';

                public function getName(): ?string
                {
                    return $this->name;
                }

                public function setName(?string $name): void
                {
                    $this->name = $name;
                }
            },
            'parsers' => [
                new ReflectionParser(),
                new VisibilityAwarePropertyAccessGuesser(),
            ],
            'expectedPropertyCount' => 1,
            'accessType' => [
                'public' => false,
                'hasGetter' => true,
                'hasSetter' => true,
            ],
        ];
        yield 'MissingGetter' => [
            'class' => new class() {
                private ?string $name;

                public function setName(?string $name): void
                {
                    $this->name = $name;
                }
            },
            'parsers' => [
                new ReflectionParser(),
                new VisibilityAwarePropertyAccessGuesser(),
            ],
            'expectedPropertyCount' => 1,
            'accessType' => [
                'public' => false,
                'hasGetter' => false,
                'hasSetter' => true,
            ],
        ];
        yield 'MissingSetter' => [
            'class' => new class() {
                private ?string $name = 'php';

                public function getName(): ?string
                {
                    return $this->name;
                }
            },
            'parsers' => [
                new ReflectionParser(),
                new VisibilityAwarePropertyAccessGuesser(),
            ],
            'expectedPropertyCount' => 1,
            'accessType' => [
                'public' => false,
                'hasGetter' => true,
                'hasSetter' => false,
            ],
        ];
    }
}
