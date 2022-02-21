<?php


namespace App\Chat;


class ChatMessage implements \JsonSerializable
{
    private $id;
    private $senderId;
    private $recipientId;
    private $isRead;
    private $content;

    /**
     * @var \DateTimeImmutable
     */
    private $cratedAt;

    public function __construct(
        ?int $id,
        int $senderId,
        int $recipientId,
        int $isRead,
        string $content,
        \DateTimeImmutable $cratedAt
    ) {
        $this->id = $id;
        $this->senderId = $senderId;
        $this->recipientId = $recipientId;
        $this->isRead = $isRead;
        $this->content = $content;
        $this->cratedAt = $cratedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSenderId(): int
    {
        return $this->senderId;
    }

    public function getRecipientId(): int
    {
        return $this->recipientId;
    }

    public function isRead(): int
    {
        return $this->isRead;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->content,
            'user_id' => $this->senderId,
            'chat_id' => $this->recipientId,
            'date' => $this->cratedAt->format('Y-m-d H:i:s'),
        ];
    }
}