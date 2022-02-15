<?php

declare(strict_types=1);

namespace Tests\Liip\MetadataParser\ModelParser;

use JMS\Serializer\Annotation as JMS;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;

/**
 * @small
 */
class JMSParserTest extends AbstractJMSParserTest
{
    public function testReadOnlyProperty(): void
    {
        $c = new class() {
            /**
             * @JMS\ReadOnly()
             */
            private $property;
        };

        $classMetadata = new RawClassMetadata(\get_class($c));
        $this->parser->parse($classMetadata);

        $props = $classMetadata->getPropertyCollections();
        $this->assertCount(1, $props, 'Number of properties should match');

        $this->assertPropertyCollection('property', 1, $props[0]);
        $this->assertPropertyVariation('property', false, true, $props[0]->getVariations()[0]);
    }
}
