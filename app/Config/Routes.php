<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('chat', 'Home::index');
$routes->get('admin', 'Home::index');

$routes->group('api', static function ($routes) {
    $routes->post('auth/register', 'Api\AuthController::register');
    $routes->post('auth/login', 'Api\AuthController::login');

    $routes->get('auth/me', 'Api\AuthController::me', ['filter' => 'jwt']);
    $routes->get('plans', 'Api\PlanController::index');

    $routes->get('chats', 'Api\ChatController::index');
    $routes->post('chats', 'Api\ChatController::create');
    $routes->get('chats/(:num)', 'Api\ChatController::show/$1');
    $routes->post('chat', 'Api\ChatController::chat');
    $routes->post('chat/stream', 'Api\ChatController::stream');

    $routes->get('admin/plans', 'Api\AdminPlanController::index', ['filter' => 'authadmin']);
    $routes->post('admin/plans', 'Api\AdminPlanController::create', ['filter' => 'authadmin']);
    $routes->put('admin/plans/(:num)', 'Api\AdminPlanController::update/$1', ['filter' => 'authadmin']);
    $routes->delete('admin/plans/(:num)', 'Api\AdminPlanController::delete/$1', ['filter' => 'authadmin']);

    $routes->get('admin/documents', 'Api\AdminDocumentController::index', ['filter' => 'authadmin']);
    $routes->post('admin/documents/upload', 'Api\AdminDocumentController::upload', ['filter' => 'authadmin']);
    $routes->post('document/upload', 'Api\DocumentController::upload');
});
