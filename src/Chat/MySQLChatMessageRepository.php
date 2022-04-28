<?php


namespace App\Chat;


class MySQLChatMessageRepository implements ChatMessageRepository
{
    /**
     * @var \PDO
     */
    private $connection;

    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
    }


    public function save(ChatMessage $message): void
    {
        $sql = $message->getId() ? 'UPDATE chat_messages SET' : 'INSERT INTO chat_messages SET';
        $sql .= sprintf(
            ' sender_id=%d, recipient_id=%s, is_read=%d, content=\'%s\'',
            $message->getSenderId(),
            $message->getRecipientId(),
            $message->isRead(),
            addslashes($message->getContent())
        );

        if ($message->getId()) {
            $sql .= sprintf(' WHERE id =\'%d\'', $message->getId());
        }

        $this->connection->exec($sql);

        if (!$message->getId()) {
            $message->setId((int) $this->connection->lastInsertId());
        }
    }

    public function getAllByRecipientIdAndSenderId(int $recipientId, int $senderId): array
    {
        $sql = '
SELECT id, sender_id, recipient_id, is_read, content, created_at 
FROM chat_messages 
WHERE recipient_id ='.$recipientId.' AND sender_id = '.$senderId.' OR (recipient_id ='.$senderId.' AND sender_id = '.$recipientId.')
ORDER BY id ASC
LIMIT 1000';
        $result = [];
        $res = $this->connection->query($sql);

        while ($row = $res->fetch()) {
            $result[] = new ChatMessage(
                $row['id'],
                $row['sender_id'],
                $row['recipient_id'],
                $row['is_read'],
                $row['content'],
                \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['created_at'])
            );
        }

        return $result;
    }

    public function getUnreadCount(int $recipientId, int $senderId): int
    {
        $sql = '
SELECT count(id) as count
FROM chat_messages 
WHERE recipient_id ='.$recipientId.' AND sender_id = '.$senderId.' AND is_read = 0';
        $res = $this->connection->query($sql);

        return $res->fetch()['count'];
    }

  public function getLastMessage(int $recipientId, int $senderId)
  {
    $sql = '
SELECT content, created_at
FROM chat_messages 
WHERE (recipient_id ='.$recipientId.' AND sender_id = '.$senderId.')
OR (recipient_id ='.$senderId.' AND sender_id = '.$recipientId.') 
ORDER BY `created_at` DESC LIMIT 1';
    $res = $this->connection->query($sql);

    return $res->fetch();
  }

    public function resetUnreadCount(int $recipientId, int $senderId): void
    {
        $sql = 'UPDATE chat_messages SET is_read = 1 WHERE recipient_id ='.$recipientId.' AND sender_id = '.$senderId.' AND is_read = 0';

        $this->connection->exec($sql);
    }
}