<?php


namespace App\Chat;


class ChatMainState implements \JsonSerializable
{
    private $users;
    /**
     * @var int
     */
    private $userId;

    public function __construct(array $users, int $userId)
    {
        $this->users = $users;
        $this->userId = $userId;
    }

    public function jsonSerialize(): array
    {
        return [
            'api' => [
                "call" => [

                ],
                "chat" => [
                ],
                "message" => [
                    "Add" => 1,
                    "GetAll" => 1,
                    "Remove" => 1,
                    "ResetCounter" => 1,
                ]
            ],
            'data' => [
                'chats' => $this->users,
                'user' => $this->userId,
                'users' => $this->users
            ],
            'websocket' => false
        ];
    }
}