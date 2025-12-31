<?php

declare(strict_types=1);

namespace PhpAgent\Session\Storage;

interface StorageInterface
{
    public function save(string $id, array $data): void;

    public function load(string $id): ?array;

    public function delete(string $id): void;

    public function pruneOld(int $days): int;
}
