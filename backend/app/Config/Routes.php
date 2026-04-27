<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->post('auth/register', '\App\Modules\Auth\Controllers\AuthController::register');
$routes->post('auth/token', '\App\Modules\Auth\Controllers\AuthController::token', ['filter' => 'oauth_rate_limit']);
$routes->post('auth/revoke', '\App\Modules\Auth\Controllers\AuthController::revoke', ['filter' => 'oauth_rate_limit']);

$routes->get('users/(:segment)', '\App\Modules\User\Controllers\UserController::show/$1', ['filter' => 'auth']);
$routes->put('users/(:segment)/profile', '\App\Modules\User\Controllers\UserController::update/$1', ['filter' => 'auth']);
$routes->delete('users/(:segment)', '\App\Modules\User\Controllers\UserController::destroy/$1', ['filter' => 'auth']);

$routes->get('health', '\App\Modules\Auth\Controllers\HealthController::index');
$routes->get('/', 'Home::index');
