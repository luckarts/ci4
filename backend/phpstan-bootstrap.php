<?php

// Define CodeIgniter constants for PHPStan analysis
define('FCPATH', __DIR__ . '/public/');
define('ROOTPATH', dirname(__DIR__) . '/');
define('APPPATH', __DIR__ . '/app/');
define('WRITEPATH', __DIR__ . '/writable/');
define('SYSTEMPATH', __DIR__ . '/vendor/codeigniter4/framework/system/');
define('ENVIRONMENT', 'development');

// Mock helper functions
if (!function_exists('is_cli')) {
    function is_cli(): bool { return false; }
}

if (!function_exists('view')) {
    function view(string $name, array $data = [], array $options = []): string { return ''; }
}

if (!function_exists('service')) {
    function service(string $name, ...$args) { return null; }
}

if (!function_exists('clean_path')) {
    function clean_path(string $path): string { return $path; }
}

if (!function_exists('esc')) {
    function esc($data, $context = 'html', $encoding = null) { return $data; }
}

if (!function_exists('lang')) {
    function lang(string $line, array $args = [], ?string $locale = null): string { return $line; }
}
