<?php

declare(strict_types=1);

namespace PhpAgent\Session;

use PhpAgent\Response;

class Session
{
    private string $id;
    private array $messages = [];
    private array $metadata = [];
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function addMessage(array $message): void
    {
        $this->messages[] = $message;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function send(string $message): Response
    {
        // This method should be implemented by the Agent
        // For now, throw an exception indicating this should be called through Agent
        throw new \RuntimeException(
            'Session::send() should not be called directly. ' .
            'Use Agent::chat() or create a session-aware wrapper.'
        );
    }

    public function clear(): void
    {
        $this->messages = [];
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'messages' => $this->messages,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c')
        ];
    }

    public static function fromArray(array $data): self
    {
        $session = new self($data['id']);
        $session->messages = $data['messages'] ?? [];
        $session->metadata = $data['metadata'] ?? [];
        $session->createdAt = new \DateTimeImmutable($data['created_at']);
        $session->updatedAt = new \DateTimeImmutable($data['updated_at']);

        return $session;
    }
}
