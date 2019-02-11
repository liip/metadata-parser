<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

final class PropertyAccessor implements \JsonSerializable
{
    /**
     * @var string|null
     */
    private $getterMethod;

    /**
     * @var string|null
     */
    private $setterMethod;

    public function __construct(?string $getterMethod, ?string $setterMethod)
    {
        $this->getterMethod = $getterMethod;
        $this->setterMethod = $setterMethod;
    }

    public static function none(): self
    {
        return new self(null, null);
    }

    public function isDefined(): bool
    {
        return $this->hasGetterMethod() || $this->hasGetterMethod();
    }

    public function hasGetterMethod(): bool
    {
        return null !== $this->getterMethod;
    }

    public function getGetterMethod(): ?string
    {
        return $this->getterMethod;
    }

    public function hasSetterMethod(): bool
    {
        return null !== $this->setterMethod;
    }

    public function getSetterMethod(): ?string
    {
        return $this->setterMethod;
    }

    public function jsonSerialize(): array
    {
        return \array_filter([
            'getter_method' => $this->getterMethod,
            'setter_method' => $this->setterMethod,
        ]);
    }
}
