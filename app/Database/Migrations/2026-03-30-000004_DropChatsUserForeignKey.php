<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropChatsUserForeignKey extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('chats')) {
            return;
        }

        foreach (['chats_user_id_foreign', 'fk_chats_user'] as $constraint) {
            try {
                $this->db->query(sprintf('ALTER TABLE `chats` DROP FOREIGN KEY `%s`', $constraint));
            } catch (\Throwable $_) {
                // Ignore missing constraints so the migration stays idempotent.
            }
        }

        $this->db->query('ALTER TABLE `chats` MODIFY `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down()
    {
        // Intentionally left blank to avoid restoring login-dependent schema.
    }
}
