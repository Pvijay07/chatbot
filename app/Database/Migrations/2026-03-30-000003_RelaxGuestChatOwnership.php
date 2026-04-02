<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RelaxGuestChatOwnership extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('chats')) {
            return;
        }

        $database = $this->db->getDatabase();
        $constraints = $this->db->query(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database, 'chats', 'user_id']
        )->getResultArray();

        foreach ($constraints as $constraint) {
            $name = $constraint['CONSTRAINT_NAME'] ?? null;
            if (!is_string($name) || $name === '') {
                continue;
            }

            $this->db->query(sprintf('ALTER TABLE `chats` DROP FOREIGN KEY `%s`', str_replace('`', '``', $name)));
        }

        $this->db->query('ALTER TABLE `chats` MODIFY `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down()
    {
        // Intentionally left blank to avoid recreating auth dependencies.
    }
}
