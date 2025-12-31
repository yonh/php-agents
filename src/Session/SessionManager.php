<?php

declare(strict_types=1);

namespace PhpAgent\Session;

use PhpAgent\Session\Storage\StorageInterface;

class SessionManager
{
    private StorageInterface $storage;
    private array $sessions = [];

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    public function create(?string $id = null): Session
    {
        $id = $id ?? $this->generateId();

        $session = new Session($id);
        $this->sessions[$id] = $session;

        return $session;
    }

    public function get(string $id): Session
    {
        if (isset($this->sessions[$id])) {
            return $this->sessions[$id];
        }

        $data = $this->storage->load($id);

        if ($data === null) {
            return $this->create($id);
        }

        $session = Session::fromArray($data);
        $this->sessions[$id] = $session;

        return $session;
    }

    public function save(Session $session): void
    {
        $this->storage->save($session->getId(), $session->toArray());
    }

    public function delete(string $id): void
    {
        unset($this->sessions[$id]);
        $this->storage->delete($id);
    }

    public function pruneOldSessions(int $days): int
    {
        return $this->storage->pruneOld($days);
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
