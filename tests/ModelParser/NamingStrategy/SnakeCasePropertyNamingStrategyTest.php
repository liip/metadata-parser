<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser\NamingStrategy;

use Liip\MetadataParser\ModelParser\NamingStrategy\SnakeCasePropertyNamingStrategy;
use PHPUnit\Framework\TestCase;

/**
 * @small
 */
class SnakeCasePropertyNamingStrategyTest extends TestCase
{
    private SnakeCasePropertyNamingStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new SnakeCasePropertyNamingStrategy();
    }

    /**
     * @dataProvider provideGetSerializedNameCases
     */
    public function testGetSerializedName(string $input, string $expected): void
    {
        $result = $this->strategy->getSerializedName($input);

        $this->assertSame($expected, $result);
    }

    public static function provideGetSerializedNameCases(): iterable
    {
        return [
            ['camelCase', 'camel_case'],
            ['foo', 'foo'],
            ['word', 'word'],
            ['wORD', 'w_o_r_d'],
            ['', ''],
            ['field1Name', 'field1_name'],
            ['longerCamelCaseName', 'longer_camel_case_name'],
        ];
    }
}
