<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCascadeConstraints extends Migration
{
    public function up()
    {
        // Drop existing constraints if they exist (from forge->addForeignKey which may not work correctly)
        try {
            $this->db->query('ALTER TABLE oauth_refresh_tokens DROP CONSTRAINT oauth_refresh_tokens_access_token_id_foreign');
        } catch (\Exception $e) {
            // Constraint doesn't exist, continue
        }

        try {
            $this->db->query('ALTER TABLE oauth_access_tokens DROP CONSTRAINT oauth_access_tokens_user_id_foreign');
        } catch (\Exception $e) {
            // Constraint doesn't exist, continue
        }

        try {
            $this->db->query('ALTER TABLE oauth_access_tokens DROP CONSTRAINT oauth_access_tokens_client_id_foreign');
        } catch (\Exception $e) {
            // Constraint doesn't exist, continue
        }

        // Add CASCADE constraints via raw SQL for PostgreSQL
        $this->db->query('
            ALTER TABLE oauth_access_tokens
            ADD CONSTRAINT fk_oauth_access_tokens_user_id
            FOREIGN KEY (user_id)
            REFERENCES users(id)
            ON DELETE CASCADE
            ON UPDATE RESTRICT
        ');

        $this->db->query('
            ALTER TABLE oauth_access_tokens
            ADD CONSTRAINT fk_oauth_access_tokens_client_id
            FOREIGN KEY (client_id)
            REFERENCES oauth_clients(id)
            ON DELETE CASCADE
            ON UPDATE RESTRICT
        ');

        $this->db->query('
            ALTER TABLE oauth_refresh_tokens
            ADD CONSTRAINT fk_oauth_refresh_tokens_access_token_id
            FOREIGN KEY (access_token_id)
            REFERENCES oauth_access_tokens(id)
            ON DELETE CASCADE
            ON UPDATE RESTRICT
        ');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE oauth_refresh_tokens DROP CONSTRAINT fk_oauth_refresh_tokens_access_token_id');
        $this->db->query('ALTER TABLE oauth_access_tokens DROP CONSTRAINT fk_oauth_access_tokens_client_id');
        $this->db->query('ALTER TABLE oauth_access_tokens DROP CONSTRAINT fk_oauth_access_tokens_user_id');
    }
}
