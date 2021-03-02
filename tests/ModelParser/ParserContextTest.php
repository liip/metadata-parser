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
        $this->assertStringContainsString('Root', $s);
        $this->assertStringNotContainsString('property1', $s);
        $this->assertStringNotContainsString('property2', $s);
    }

    public function testPush(): void
    {
        $context = new ParserContext('Root');
        $context = $context->push(new PropertyVariationMetadata('property1', true, true));
        $context = $context->push(new PropertyVariationMetadata('property2', false, true));

        $s = (string) $context;
        $this->assertStringContainsString('Root', $s);
        $this->assertStringContainsString('property1', $s);
        $this->assertStringContainsString('property2', $s);
    }
}
