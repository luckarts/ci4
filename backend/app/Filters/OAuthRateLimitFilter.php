<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class OAuthRateLimitFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Skip rate limiting in testing unless explicitly enabled via header
        $enableRateLimit = $request->getHeaderLine('X-Rate-Limit-Capacity');
        if (!$enableRateLimit && defined('CI_ENVIRONMENT') && CI_ENVIRONMENT === 'testing') {
            return null;
        }

        // Also skip if cache handler is dummy
        if ((getenv('CACHE_HANDLER') ?: 'file') === 'dummy') {
            return null;
        }

        $capacity = (int) ($enableRateLimit ?: getenv('OAUTH_RATE_LIMIT_CAPACITY') ?: 10);
        $seconds = (int) (getenv('OAUTH_RATE_LIMIT_SECONDS') ?: 60);

        $ip = $this->getClientIp($request);
        $clientId = $this->extractClientId($request);

        $key = sha1($ip . $clientId);

        $throttler = service('throttler');

        if (!$throttler->check($key, $capacity, $seconds)) {
            return service('response')
                ->setStatusCode(429)
                ->setContentType('application/json')
                ->setBody(json_encode([
                    'error' => 'server_error',
                    'error_description' => 'Rate limit exceeded',
                ]));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function getClientIp(RequestInterface $request): string
    {
        // Allow test to override IP via header to isolate rate limit keys
        $testIp = $request->getHeaderLine('X-Test-Client-IP');
        if ($testIp) {
            return $testIp;
        }

        $ip = $request->getIPAddress();
        return $ip ?: '0.0.0.0';
    }

    private function extractClientId(RequestInterface $request): string
    {
        $post = $request->getPost();
        return isset($post['client_id']) ? (string) $post['client_id'] : '';
    }
}
