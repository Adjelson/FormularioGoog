<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Router;
use App\Middleware\AuthMiddleware;

// Carregar variáveis de ambiente
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// JSON sempre
header('Content-Type: application/json; charset=utf-8');

// CORS
$allowedOriginsRaw = $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*';
$allowedOrigins = array_map('trim', explode(',', $allowedOriginsRaw));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($allowedOriginsRaw === '*' || in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
}
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-HTTP-Method-Override');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Method override (útil em alguns ambientes)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null;
if ($override && in_array(strtoupper($override), ['PUT', 'PATCH', 'DELETE'], true)) {
    $method = strtoupper($override);
}

// Inicializar Roteador
$router = new Router();

/**
 * ROTAS PÚBLICAS
 */
$router->add('POST', '/api/login', 'AuthController@login');
$router->add('POST', '/api/register', 'AuthController@register'); // dev only (controlado por env)

$router->add('GET',  '/api/public/forms/{slug}', 'FormController@getPublic');
$router->add('POST', '/api/public/uploads', 'UploadController@store'); // multipart/form-data file
$router->add('POST', '/api/public/forms/{slug}/responses', 'FormController@submitResponse');

/**
 * ROTAS ADMIN (protegidas)
 */
$router->add('GET', '/api/admin/forms/archived', 'FormController@archived', [AuthMiddleware::class]);
$router->add('GET', '/api/admin/forms-archived', 'FormController@archived', [AuthMiddleware::class]);

$router->add('GET',    '/api/admin/forms', 'FormController@index', [AuthMiddleware::class]);
$router->add('POST',   '/api/admin/forms', 'FormController@store', [AuthMiddleware::class]);

$router->add('GET',    '/api/admin/forms/{id}', 'FormController@show', [AuthMiddleware::class]);
$router->add('PUT',    '/api/admin/forms/{id}', 'FormController@update', [AuthMiddleware::class]);
$router->add('POST',   '/api/admin/forms/{id}/publish', 'FormController@publish', [AuthMiddleware::class]);
$router->add('POST', '/api/admin/questions/{id}/archive', 'FormController@archiveQuestion', [AuthMiddleware::class]);
$router->add('POST', '/api/admin/forms/{id}/archive', 'FormController@archive', [AuthMiddleware::class]);
$router->add('POST', '/api/admin/forms/{id}/unpublish', 'FormController@unpublish', [AuthMiddleware::class]);

$router->add('POST',   '/api/admin/forms/{id}/questions', 'QuestionController@store', [AuthMiddleware::class]);
$router->add('PUT',    '/api/admin/questions/{id}', 'QuestionController@update', [AuthMiddleware::class]);

$router->add('POST',   '/api/admin/questions/{id}/options', 'OptionController@store', [AuthMiddleware::class]);
$router->add('PUT',    '/api/admin/options/{id}', 'OptionController@update', [AuthMiddleware::class]);
$router->add('DELETE', '/api/admin/options/{id}', 'OptionController@destroy', [AuthMiddleware::class]);

$router->add('GET',    '/api/admin/forms/{id}/responses', 'ResponseController@index', [AuthMiddleware::class]);
$router->add('GET',    '/api/admin/responses/{rid}', 'ResponseController@show', [AuthMiddleware::class]);
$router->add('GET',    '/api/admin/files/{uploadId}', 'FileController@download', [AuthMiddleware::class]);

// Despachar a rota
$uri = $_SERVER['REQUEST_URI'] ?? '/';

// Lógica para lidar com subdiretórios no XAMPP
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = str_replace('/index.php', '', $scriptName);
if ($basePath !== '' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

if (empty($uri)) $uri = '/';

$router->dispatch($method, $uri);
