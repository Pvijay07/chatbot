<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePetsfolioTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 160,
            ],
            'password_hash' => [
                'type' => 'TEXT',
            ],
            'role' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'user',
            ],
            'preferred_locale' => [
                'type'       => 'VARCHAR',
                'constraint' => 5,
                'default'    => 'en',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('users', true);

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'pet_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'last_message_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('chats', true);

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'chat_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'sender' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'message' => [
                'type' => 'LONGTEXT',
            ],
            'language' => [
                'type'       => 'VARCHAR',
                'constraint' => 5,
                'default'    => 'en',
            ],
            'sources_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('chat_id');
        $this->forge->addForeignKey('chat_id', 'chats', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('messages', true);

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'pet_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
            ],
            'name_en' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'name_hi' => [
                'type'       => 'VARCHAR',
                'constraint' => 160,
            ],
            'summary_en' => [
                'type' => 'TEXT',
            ],
            'summary_hi' => [
                'type' => 'TEXT',
            ],
            'price_monthly' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'annual_limit' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'deductible' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'reimbursement_percent' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'waiting_period_days' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'claim_steps_en' => [
                'type' => 'TEXT',
            ],
            'claim_steps_hi' => [
                'type' => 'TEXT',
            ],
            'exclusions_en' => [
                'type' => 'TEXT',
            ],
            'exclusions_hi' => [
                'type' => 'TEXT',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('pet_type');
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('insurance_plans', true);

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
            ],
            'file_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'file_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'mime_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'language' => [
                'type'       => 'VARCHAR',
                'constraint' => 5,
                'default'    => 'en',
            ],
            'content_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'uploaded_by' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('language');
        $this->forge->addForeignKey('uploaded_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('insurance_documents', true);

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'document_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'chunk_index' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'language' => [
                'type'       => 'VARCHAR',
                'constraint' => 5,
                'default'    => 'en',
            ],
            'content' => [
                'type' => 'LONGTEXT',
            ],
            'token_count' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0,
            ],
            'keywords' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('document_id');
        $this->forge->addKey('language');
        $this->forge->addForeignKey('document_id', 'insurance_documents', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('insurance_document_chunks', true);
    }

    public function down()
    {
        $this->forge->dropTable('insurance_document_chunks', true);
        $this->forge->dropTable('insurance_documents', true);
        $this->forge->dropTable('insurance_plans', true);
        $this->forge->dropTable('messages', true);
        $this->forge->dropTable('chats', true);
        $this->forge->dropTable('users', true);
    }
}
