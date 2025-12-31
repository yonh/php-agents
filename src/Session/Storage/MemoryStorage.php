<?php

declare(strict_types=1);

namespace PhpAgent\Session\Storage;

class MemoryStorage implements StorageInterface
{
    private array $storage = [];

    public function save(string $id, array $data): void
    {
        $this->storage[$id] = $data;
    }

    public function load(string $id): ?array
    {
        return $this->storage[$id] ?? null;
    }

    public function delete(string $id): void
    {
        unset($this->storage[$id]);
    }

    public function pruneOld(int $days): int
    {
        return 0;
    }
}
