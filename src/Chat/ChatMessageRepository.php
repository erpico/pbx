<?php


namespace App\Chat;


interface ChatMessageRepository
{
    public function save(ChatMessage $message): void;

    public function getAllByRecipientIdAndSenderId(int $recipientId, int $senderId): array;

    public function getUnreadCount(int $recipientId, int $senderId): int;

    public function resetUnreadCount(int $recipientId, int $senderId): void;

}