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
        $this->assertStringContainsString('Root', $s);
        $this->assertStringNotContainsString('property1', $s);
        $this->assertStringNotContainsString('property2', $s);
    }

    public function testPush(): void
    {
        $context = new RecursionContext('Root');
        $context = $context->push(new PropertyMetadata('property1', 'property1'));
        $context = $context->push(new PropertyMetadata('property2', 'property2'));

        $s = (string) $context;
        $this->assertStringContainsString('Root', $s);
        $this->assertStringContainsString('property1', $s);
        $this->assertStringContainsString('property2', $s);
    }

    public function testMatchesEmpty(): void
    {
        $context = new RecursionContext('Root');

        $this->assertFalse($context->matches([]));
        $this->assertFalse($context->matches(['foo', 'bar', 'baz']));
    }

    public function testMatches(): void
    {
        $context = new RecursionContext('Root');
        $context = $context->push(new PropertyMetadata('property1', 'property1'));
        $context = $context->push(new PropertyMetadata('property2', 'property2'));

        $this->assertFalse($context->matches(['Root', 'property2']));
        $this->assertFalse($context->matches(['property2', 'property1']));
        $this->assertTrue($context->matches(['Root', 'property1']));
        $this->assertTrue($context->matches(['Root', 'property1', 'property2']));
        $this->assertTrue($context->matches(['property1', 'property2']));
        $this->assertTrue($context->matches(['property2']));
    }

    public function testMatchesWildcard(): void
    {
        $context = new RecursionContext('Root');
        $context = $context->push(new PropertyMetadata('property1', 'property1'));
        $context = $context->push(new PropertyMetadata('property2', 'property2'));

        $this->assertFalse($context->matches(['Root', '*', 'property1']));
        $this->assertFalse($context->matches(['*', 'property1']));
        $this->assertTrue($context->matches(['Root', '*']));
        $this->assertTrue($context->matches(['Root', '*', 'property2']));
    }
}
