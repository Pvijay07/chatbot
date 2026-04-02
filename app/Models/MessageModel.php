<?php

namespace App\Models;

use CodeIgniter\Model;

class MessageModel extends Model
{
    protected $table = 'messages';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = false;
    protected $allowedFields = [
        'chat_id',
        'sender',
        'message',
        'language',
        'sources_json',
        'created_at',
    ];

    public function getRecentContext(int $chatId, int $limit = 5): array
    {
        $messages = $this
            ->where('chat_id', $chatId)
            ->orderBy('id', 'DESC')
            ->findAll($limit);

        return array_reverse($messages);
    }

    public function getChatMessages(int $chatId): array
    {
        return $this
            ->where('chat_id', $chatId)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function getLastMessagesForChats(array $chatIds): array
    {
        $chatIds = array_values(array_filter(array_map('intval', $chatIds), static fn(int $id): bool => $id > 0));
        if ($chatIds === []) {
            return [];
        }

        $subquery = $this->db->table($this->table)
            ->select('chat_id, MAX(id) AS max_id')
            ->whereIn('chat_id', $chatIds)
            ->groupBy('chat_id');

        $rows = $this->db->table($this->table . ' m')
            ->select('m.chat_id, m.message')
            ->join('(' . $subquery->getCompiledSelect() . ') latest', 'latest.max_id = m.id AND latest.chat_id = m.chat_id', 'inner', false)
            ->get()
            ->getResultArray();

        $messages = [];
        foreach ($rows as $row) {
            $messages[(int) $row['chat_id']] = (string) ($row['message'] ?? '');
        }

        return $messages;
    }
}
