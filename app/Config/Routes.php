<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->post('api/chatbot/query', 'Api\ChatbotController::query');
// Ollama chat endpoint
$routes->post('api/chat', 'Api\ChatController::chat');
$routes->post('api/voice/process', 'Api\VoiceController::process');
$routes->post('api/document/upload', 'Api\DocumentController::upload');

// Chat UI and language switch
$routes->get('chat', 'Chat::index');
$routes->post('language', 'Chat::setLanguage');

// Local push endpoint (for app to forward messages to WebSocket server)
$routes->post('push', 'Push::index');