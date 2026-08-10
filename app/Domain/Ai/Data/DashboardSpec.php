<?php

namespace App\Domain\Ai\Data;

use JsonSerializable;

/**
 * Provider-neutral representation. Instances remain untrusted until they pass
 * through DashboardSpecValidator.
 */
final readonly class DashboardSpec implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $definition
     */
    private function __construct(private array $definition) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromArray(array $definition): self
    {
        return new self($definition);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->definition;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
