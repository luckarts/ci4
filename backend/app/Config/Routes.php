<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->post('auth/register', 'AuthController::register');
$routes->post('auth/token', 'AuthController::token');
$routes->post('auth/revoke', 'AuthController::revoke');

$routes->get('users/(:segment)', 'UserController::show/$1', ['filter' => 'auth']);
$routes->put('users/(:segment)/profile', 'UserController::update/$1', ['filter' => 'auth']);

$routes->get('/', 'Home::index');
