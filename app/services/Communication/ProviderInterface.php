<?php

declare(strict_types=1);

namespace App\Services\Communication;

interface ProviderInterface
{
    public function key(): string;
    public function channel(): string;
    public function label(): string;
    public function configured(): bool;
    public function send(array $message): ProviderResult;
}
