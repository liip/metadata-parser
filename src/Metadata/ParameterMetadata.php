<?php

declare(strict_types=1);

namespace Liip\MetadataParser\Metadata;

final class ParameterMetadata implements \JsonSerializable
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $required;

    /**
     * @var mixed The default value can be of any type
     */
    private $defaultValue;

    public function __construct(string $name, bool $required, $defaultValue = null)
    {
        $this->name = $name;
        $this->required = $required;
        $this->defaultValue = $defaultValue;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public static function fromReflection(\ReflectionParameter $reflParameter): self
    {
        if ($reflParameter->isOptional()) {
            return new self($reflParameter->getName(), false, $reflParameter->getDefaultValue());
        }

        return new self($reflParameter->getName(), true);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @throws \BadMethodCallException if the parameter is required and therefore has no default value
     */
    public function getDefaultValue()
    {
        if ($this->required) {
            throw new \BadMethodCallException(sprintf('Parameter %s is required and therefore has no default value', (string) $this));
        }

        return $this->defaultValue;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'required' => $this->required,
            'default_value' => $this->defaultValue,
        ];
    }
}
