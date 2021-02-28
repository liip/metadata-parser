<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser;

use Liip\MetadataParser\ModelParser\ParserContext;
use Liip\MetadataParser\ModelParser\RawMetadata\PropertyVariationMetadata;
use PHPUnit\Framework\TestCase;

/**
 * @small
 */
class ParserContextTest extends TestCase
{
    public function testEmpty(): void
    {
        $context = new ParserContext('Root');

        $s = (string) $context;
        static::assertStringContainsString('Root', $s);
        static::assertStringNotContainsString('property1', $s);
        static::assertStringNotContainsString('property2', $s);
    }

    public function testPush(): void
    {
        $context = new ParserContext('Root');
        $context = $context->push(new PropertyVariationMetadata('property1', true, true));
        $context = $context->push(new PropertyVariationMetadata('property2', false, true));

        $s = (string) $context;
        static::assertStringContainsString('Root', $s);
        static::assertStringContainsString('property1', $s);
        static::assertStringContainsString('property2', $s);
    }
}
