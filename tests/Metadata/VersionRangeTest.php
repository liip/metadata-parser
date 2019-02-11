<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\Metadata;

use Liip\MetadataParser\Metadata\VersionRange;
use PHPUnit\Framework\TestCase;

/**
 * @small
 */
class VersionRangeTest extends TestCase
{
    public function testFactoryMethod(): void
    {
        $versionRange = VersionRange::all();
        $this->assertNull($versionRange->getSince());
        $this->assertNull($versionRange->getUntil());
    }

    public function testDefined(): void
    {
        $versionRange = new VersionRange(null, null);
        $this->assertFalse($versionRange->isDefined());

        $versionRange = new VersionRange('1', null);
        $this->assertTrue($versionRange->isDefined());

        $versionRange = new VersionRange(null, '1');
        $this->assertTrue($versionRange->isDefined());
    }

    public function testWithSince(): void
    {
        $version = new VersionRange('1', '2');
        $new = $version->withSince('2');
        $this->assertNotSame($version, $new);
        $this->assertSame('2', $new->getSince());
        $this->assertSame('1', $version->getSince());
    }

    public function testWithUntil(): void
    {
        $version = new VersionRange('1', '2');
        $new = $version->withUntil('3');
        $this->assertNotSame($version, $new);
        $this->assertSame('3', $new->getUntil());
        $this->assertSame('2', $version->getUntil());
    }

    public function provideVersionRanges()
    {
        return [
            'null is lowest and highest' => [
                new VersionRange(null, null),
                '0',
                true,
            ],
            'lower bound' => [
                new VersionRange('0', '1'),
                '0',
                true,
            ],
            'somewhere' => [
                new VersionRange('1', '2'),
                '1',
                true,
            ],
            'upper bound' => [
                new VersionRange('1', '2'),
                '2',
                true,
            ],
            'below' => [
                new VersionRange('2', '3'),
                '1',
                false,
            ],
            'above' => [
                new VersionRange('1', '2'),
                '4',
                false,
            ],
        ];
    }

    /**
     * @dataProvider provideVersionRanges
     */
    public function testIsIncluded(VersionRange $versionRange, string $version, bool $expected): void
    {
        $this->assertSame($expected, $versionRange->isIncluded($version));
    }

    public function provideVersionRangesForLower()
    {
        return [
            'same null' => [
                new VersionRange(null, null),
                new VersionRange(null, null),
                false,
            ],
            'same value' => [
                new VersionRange('1', null),
                new VersionRange('1', null),
                false,
            ],
            'null is lowest' => [
                new VersionRange(null, null),
                new VersionRange('1', null),
                true,
            ],
            'other null is lowest' => [
                new VersionRange('1', null),
                new VersionRange(null, null),
                false,
            ],
            'first is lower' => [
                new VersionRange('1', null),
                new VersionRange('2', null),
                true,
            ],
            'second is lower' => [
                new VersionRange('2', null),
                new VersionRange('1', null),
                false,
            ],
        ];
    }

    /**
     * @dataProvider provideVersionRangesForLower
     */
    public function testAllowsLowerThan(VersionRange $versionRange, VersionRange $other, bool $lower): void
    {
        $this->assertSame($lower, $versionRange->allowsLowerThan($other));
    }

    public function provideVersionRangesForHigher()
    {
        return [
            'same null' => [
                new VersionRange(null, null),
                new VersionRange(null, null),
                false,
            ],
            'same value' => [
                new VersionRange(null, '1'),
                new VersionRange(null, '1'),
                false,
            ],
            'null is highest' => [
                new VersionRange(null, null),
                new VersionRange(null, '1'),
                false,
            ],
            'other null is highest' => [
                new VersionRange(null, '1'),
                new VersionRange(null, null),
                true,
            ],
            'second value is higher' => [
                new VersionRange(null, '1'),
                new VersionRange(null, '2'),
                false,
            ],
            'first value is higher' => [
                new VersionRange(null, '2'),
                new VersionRange(null, '1'),
                true,
            ],
        ];
    }

    /**
     * @dataProvider provideVersionRangesForHigher
     */
    public function testAllowsHigherThan(VersionRange $versionRange, VersionRange $other, bool $higher): void
    {
        $this->assertSame($higher, $versionRange->allowsHigherThan($other));
    }
}
