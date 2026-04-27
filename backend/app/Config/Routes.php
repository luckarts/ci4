<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->post('auth/register', 'AuthController::register');
$routes->post('auth/token', 'AuthController::token', ['filter' => 'oauth_rate_limit']);
$routes->post('auth/revoke', 'AuthController::revoke', ['filter' => 'oauth_rate_limit']);

$routes->get('users/(:segment)', 'UserController::show/$1', ['filter' => 'auth']);
$routes->put('users/(:segment)/profile', 'UserController::update/$1', ['filter' => 'auth']);
$routes->delete('users/(:segment)', 'UserController::destroy/$1', ['filter' => 'auth']);

$routes->get('/', 'Home::index');
