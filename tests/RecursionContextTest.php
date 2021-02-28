<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser;

use Liip\MetadataParser\Metadata\PropertyMetadata;
use Liip\MetadataParser\RecursionContext;
use PHPUnit\Framework\TestCase;

/**
 * @small
 */
class RecursionContextTest extends TestCase
{
    public function testEmpty(): void
    {
        $context = new RecursionContext('Root');

        $s = (string) $context;
        static::assertStringContainsString('Root', $s);
        static::assertStringNotContainsString('property1', $s);
        static::assertStringNotContainsString('property2', $s);
    }

    public function testPush(): void
    {
        $context = new RecursionContext('Root');
        $context = $context->push(new PropertyMetadata('property1', 'property1'));
        $context = $context->push(new PropertyMetadata('property2', 'property2'));

        $s = (string) $context;
        static::assertStringContainsString('Root', $s);
        static::assertStringContainsString('property1', $s);
        static::assertStringContainsString('property2', $s);
    }

    public function testMatchesEmpty(): void
    {
        $context = new RecursionContext('Root');

        static::assertFalse($context->matches([]));
        static::assertFalse($context->matches(['foo', 'bar', 'baz']));
    }

    public function testMatches(): void
    {
        $context = new RecursionContext('Root');
        $context = $context->push(new PropertyMetadata('property1', 'property1'));
        $context = $context->push(new PropertyMetadata('property2', 'property2'));

        static::assertFalse($context->matches(['Root', 'property2']));
        static::assertFalse($context->matches(['property2', 'property1']));
        static::assertTrue($context->matches(['Root', 'property1']));
        static::assertTrue($context->matches(['Root', 'property1', 'property2']));
        static::assertTrue($context->matches(['property1', 'property2']));
        static::assertTrue($context->matches(['property2']));
    }

    public function testMatchesWildcard(): void
    {
        $context = new RecursionContext('Root');
        $context = $context->push(new PropertyMetadata('property1', 'property1'));
        $context = $context->push(new PropertyMetadata('property2', 'property2'));

        static::assertFalse($context->matches(['Root', '*', 'property1']));
        static::assertFalse($context->matches(['*', 'property1']));
        static::assertTrue($context->matches(['Root', '*']));
        static::assertTrue($context->matches(['Root', '*', 'property2']));
    }
}
