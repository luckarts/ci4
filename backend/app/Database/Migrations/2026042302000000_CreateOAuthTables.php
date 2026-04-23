<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOAuthTables extends Migration
{
    public function up()
    {
        // oauth_clients table
        $this->forge->addField([
            'id' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => false,
            ],
            'secret' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'redirect_uris' => [
                'type'   => 'TEXT',
                'null'   => true,
            ],
            'is_confidential' => [
                'type'    => 'BOOLEAN',
                'default' => false,
                'null'    => false,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('oauth_clients');

        // oauth_access_tokens table
        $this->forge->addField([
            'id' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'user_id' => [
                'type'       => 'UUID',
                'constraint' => 36,
                'null'       => true,
            ],
            'client_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => false,
            ],
            'scopes' => [
                'type'   => 'TEXT',
                'null'   => true,
            ],
            'revoked' => [
                'type'    => 'BOOLEAN',
                'default' => false,
                'null'    => false,
            ],
            'expires_at' => [
                'type'   => 'TIMESTAMP',
                'null'   => false,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('client_id', 'oauth_clients', 'id', '', 'CASCADE');
        $this->forge->createTable('oauth_access_tokens');

        // oauth_refresh_tokens table
        $this->forge->addField([
            'id' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'access_token_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'revoked' => [
                'type'    => 'BOOLEAN',
                'default' => false,
                'null'    => false,
            ],
            'expires_at' => [
                'type'   => 'TIMESTAMP',
                'null'   => false,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('access_token_id', 'oauth_access_tokens', 'id', '', 'CASCADE');
        $this->forge->createTable('oauth_refresh_tokens');

        // oauth_scopes table
        $this->forge->addField([
            'id' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => false,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('oauth_scopes');

        // Seed oauth_scopes
        $this->db->table('oauth_scopes')->insert([
            ['id' => 'profile'],
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('oauth_refresh_tokens');
        $this->forge->dropTable('oauth_access_tokens');
        $this->forge->dropTable('oauth_scopes');
        $this->forge->dropTable('oauth_clients');
    }
}
