<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class OAuthSetupCommand extends BaseCommand
{
    protected $group = 'OAuth2';
    protected $name = 'oauth:setup';
    protected $description = 'Seed OAuth2 client and generate RSA keys if missing';
    protected $usage = 'oauth:setup';

    public function run(array $params = [])
    {
        $db = \Config\Database::connect();

        // Generate RSA keys if missing
        $this->generateRsaKeys();

        // Generate Defuse encryption key if missing
        $this->generateDefuseKey();

        // Seed OAuth2 client
        $this->seedOAuthClient($db);

        CLI::write('✓ OAuth2 setup complete', 'green');
    }

    private function generateRsaKeys(): void
    {
        $keysDir = WRITEPATH . 'oauth_keys';
        $privateKeyPath = $keysDir . '/private.key';
        $publicKeyPath = $keysDir . '/public.key';

        if (file_exists($privateKeyPath) && file_exists($publicKeyPath)) {
            CLI::write('  RSA keys already exist', 'yellow');
            return;
        }

        if (!is_dir($keysDir)) {
            mkdir($keysDir, 0755, true);
        }

        // Generate RSA private key
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);
        if ($res === false) {
            throw new \RuntimeException('Failed to generate RSA key pair');
        }

        openssl_pkey_export($res, $privateKey);
        $publicKey = openssl_pkey_get_details($res)['key'];
        openssl_pkey_free($res);

        file_put_contents($privateKeyPath, $privateKey);
        file_put_contents($publicKeyPath, $publicKey);

        chmod($privateKeyPath, 0600);
        chmod($publicKeyPath, 0644);

        CLI::write('  ✓ Generated RSA keys', 'green');
    }

    private function generateDefuseKey(): void
    {
        $envPath = ROOTPATH . '.env';
        $envTestPath = ROOTPATH . '.env.test';

        // Check if key already exists
        $envContent = file_get_contents($envPath);
        if (strpos($envContent, 'OAUTH_ENCRYPTION_KEY=') !== false) {
            CLI::write('  Defuse key already set', 'yellow');
            return;
        }

        // Generate Defuse key
        $defuseKey = \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString();

        // Append to .env
        file_put_contents($envPath, "\nOAUTH_ENCRYPTION_KEY=$defuseKey\n", FILE_APPEND);
        file_put_contents($envTestPath, "\nOAUTH_ENCRYPTION_KEY=$defuseKey\n", FILE_APPEND);

        CLI::write('  ✓ Generated Defuse encryption key', 'green');
    }

    private function seedOAuthClient($db): void
    {
        $clientId = 'app_client';
        $clientSecret = bin2hex(random_bytes(32));

        // Check if client already exists
        $existing = $db->table('oauth_clients')
            ->where('id', $clientId)
            ->get()
            ->getRow();

        if ($existing) {
            CLI::write('  OAuth2 client already exists', 'yellow');
            return;
        }

        $db->table('oauth_clients')->insert([
            'id' => $clientId,
            'secret' => $clientSecret,
            'name' => 'API Client',
            'redirect_uris' => '',
            'is_confidential' => true,
        ]);

        // Store secret in .env
        $envPath = ROOTPATH . '.env';
        $envContent = file_get_contents($envPath);

        if (strpos($envContent, 'OAUTH_CLIENT_ID=') === false) {
            file_put_contents($envPath, "\nOAUTH_CLIENT_ID=$clientId\n", FILE_APPEND);
        }

        if (strpos($envContent, 'OAUTH_CLIENT_SECRET=') === false) {
            file_put_contents($envPath, "OAUTH_CLIENT_SECRET=$clientSecret\n", FILE_APPEND);
        }

        // Also add to .env.test
        $envTestPath = ROOTPATH . '.env.test';
        $envTestContent = file_get_contents($envTestPath);

        if (strpos($envTestContent, 'OAUTH_CLIENT_ID=') === false) {
            file_put_contents($envTestPath, "\nOAUTH_CLIENT_ID=$clientId\n", FILE_APPEND);
        }

        if (strpos($envTestContent, 'OAUTH_CLIENT_SECRET=') === false) {
            file_put_contents($envTestPath, "OAUTH_CLIENT_SECRET=$clientSecret\n", FILE_APPEND);
        }

        CLI::write('  ✓ Seeded OAuth2 client (app_client)', 'green');
    }
}
