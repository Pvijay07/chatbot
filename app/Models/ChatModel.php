<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatModel extends Model
{
    protected $table = 'chats';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'user_id',
        'title',
        'pet_type',
        'last_message_at',
    ];

    public function getUserChats(int $userId): array
    {
        return $this
            ->where('user_id', $userId)
            ->orderBy('last_message_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }
}
