<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$container = new \League\Container\Container();
$container->delegate(new \League\Container\ReflectionContainer(true));

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$remoteAdmin = $container->get(\Yuhzel\TmController\Infrastructure\RemoteAdminClient::class);

header('Content-Type: application/json');

$headers = getallheaders();
$token = $headers['X-Auth-Token'] ?? '';

if ($token !== $_ENV['api_token']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$method = $data['method'] ?? null;
$params = $data['params'] ?? [];

if (!$method) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing method']);
    exit;
}

try {
    $result = $remoteAdmin->executeCommand($method, $params);
    echo json_encode(['success' => true, 'result' => $result->toArray()]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}


//NOTE: Example usage WARNING anyone who has key is "masteradmin" Change it often
// curl -X POST http://localhost:8080/api.php \
//      -H "Content-Type: application/json" \
//      -H "X-Auth-Token: super-secret-token" \ (replace with token in $_ENV['api_token'] )
//      -d '{
//          "method": "RestartMap",
//          "params": []
//       }'


/**
 * You could build form and sned methods and params and with POST into api.php
 */
