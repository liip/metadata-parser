<?php

declare(strict_types=1);

namespace Liip\MetadataParser\ModelParser;

use Liip\MetadataParser\Exception\ParseException;
use Liip\MetadataParser\ModelParser\RawMetadata\RawClassMetadata;

interface ModelParserInterface
{
    /**
     * @throws ParseException
     */
    public function parse(RawClassMetadata $classMetadata): void;
}
