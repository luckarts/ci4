<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;

class HealthController extends Controller
{
    use ResponseTrait;

    /**
     * GET /health
     * Liveness probe — no auth, no rate limit.
     *
     * @http 200 OK      - All checks passed
     * @http 503 Service Unavailable - Database unreachable
     */
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            \Config\Database::connect()->query('SELECT 1');

            return $this->response
                ->setStatusCode(200)
                ->setJSON(['status' => 'ok', 'checks' => ['database' => 'ok']]);
        } catch (\Throwable $e) {
            return $this->response
                ->setStatusCode(503)
                ->setJSON(['status' => 'degraded', 'checks' => ['database' => 'error: ' . $e->getMessage()]]);
        }
    }
}
