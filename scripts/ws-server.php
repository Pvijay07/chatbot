<?php
// Petsfolio realtime WebSocket server.
// Start with: php scripts/ws-server.php

use App\Models\UserModel;
use CodeIgniter\Boot;
use Config\Paths;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);

require FCPATH . '../app/Config/Paths.php';

$paths = new Paths();

require $paths->systemDirectory . '/Boot.php';

if (!defined('ENVIRONMENT')) {
    $environment = $_ENV['CI_ENVIRONMENT'] ?? $_SERVER['CI_ENVIRONMENT'] ?? getenv('CI_ENVIRONMENT') ?: 'production';
    define('ENVIRONMENT', $environment);
}

Boot::bootConsole($paths);

class BroadcastServer implements MessageComponentInterface
{
    protected \SplObjectStorage $clients;
    protected \App\Services\AssistantService $assistant;
    protected \App\Services\JwtService $jwt;
    protected UserModel $users;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        $this->assistant = service('assistant');
        $this->jwt = service('jwt');
        $this->users = new UserModel();

        echo "Petsfolio WebSocket server started\n";
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true) ?: [];
        $type = $data['type'] ?? null;

        if ($type === 'ask' && !empty($data['prompt'])) {
            $this->handleAsk($from, $data);
            return;
        }

        $text = is_array($data) && isset($data['message']) ? $data['message'] : (string) $msg;

        foreach ($this->clients as $client) {
            if ($from === $client) {
                continue;
            }

            $this->send($client, [
                'type'    => 'broadcast',
                'message' => $text,
            ]);
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    public function broadcast(string|array $payload): void
    {
        foreach ($this->clients as $client) {
            $this->send($client, $payload);
        }
    }

    protected function handleAsk(ConnectionInterface $client, array $data): void
    {
        $id = isset($data['id']) && $data['id'] !== '' ? (string) $data['id'] : uniqid('petsfolio_', true);
        $prompt = trim((string) ($data['prompt'] ?? ''));
        $locale = (string) ($data['locale'] ?? 'en');
        $chatId = isset($data['chat_id']) && is_numeric($data['chat_id']) ? (int) $data['chat_id'] : null;
        $lookupUserId = isset($data['user_id']) && is_numeric($data['user_id']) && (int) $data['user_id'] > 0
            ? (int) $data['user_id']
            : null;

        if ($prompt === '') {
            $this->send($client, [
                'type'    => 'error',
                'id'      => $id,
                'message' => 'Message is required.',
            ]);
            $this->send($client, ['type' => 'done', 'id' => $id]);
            return;
        }

        try {
            $user = $this->resolveUser($data, $locale);
            service('authContext')->setUser($user);

            $result = $this->assistant->handleChatStream(
                (int) $user['id'],
                $prompt,
                function (string $chunk) use ($client, $id): void {
                    $this->send($client, [
                        'type' => 'partial',
                        'id'   => $id,
                        'text' => $chunk,
                    ]);
                },
                $chatId,
                $locale,
                $lookupUserId
            );

            $this->send($client, [
                'type' => 'done',
                'id'   => $id,
                'chat' => $result['chat'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            log_message('error', 'Petsfolio websocket chat failed: ' . $exception->getMessage());

            $this->send($client, [
                'type'    => 'error',
                'id'      => $id,
                'message' => 'Petsfolio could not process the request.',
            ]);
            $this->send($client, ['type' => 'done', 'id' => $id]);
        }
    }

    protected function resolveUser(array $data, string $locale): array
    {
        $token = trim((string) ($data['token'] ?? ''));
        if ($token !== '') {
            $payload = $this->jwt->decode($token);
            $user = $this->users->find((int) ($payload['sub'] ?? 0));

            if ($user === null) {
                throw new \RuntimeException('Authenticated user was not found.');
            }

            return $user;
        }

        $guestId = isset($data['guest_user_id']) && is_numeric($data['guest_user_id']) ? (int) $data['guest_user_id'] : 0;
        if ($guestId <= 0) {
            throw new \RuntimeException('A Petsfolio user context is required for realtime chat.');
        }

        return [
            'id'               => $guestId,
            'name'             => 'Petsfolio Guest',
            'email'            => null,
            'role'             => 'user',
            'preferred_locale' => $locale,
        ];
    }

    protected function send(ConnectionInterface $client, string|array $payload): void
    {
        $message = is_string($payload)
            ? $payload
            : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($message === false) {
            return;
        }

        try {
            $client->send($message);
        } catch (\Throwable $_) {
        }
    }
}

$loop = React\EventLoop\Factory::create();

$wsPort = 8080;
$httpPort = 8081;

$broadcast = new BroadcastServer();

$wsSocket = new React\Socket\SocketServer('0.0.0.0:' . $wsPort, [], $loop);
new Ratchet\Server\IoServer(
    new Ratchet\Http\HttpServer(
        new Ratchet\WebSocket\WsServer($broadcast)
    ),
    $wsSocket,
    $loop
);

echo "WebSocket listening on port {$wsPort}\n";

try {
    $http = new React\Http\Server(function (Psr\Http\Message\ServerRequestInterface $request) use ($broadcast) {
        $path = $request->getUri()->getPath();
        if ($request->getMethod() !== 'POST' || $path !== '/push') {
            return new React\Http\Message\Response(404, ['Content-Type' => 'text/plain'], 'Not found');
        }

        $body = (string) $request->getBody();
        $data = json_decode($body, true);
        $payload = is_array($data) ? $data : ['type' => 'broadcast', 'message' => $body];

        $broadcast->broadcast($payload);

        return new React\Http\Message\Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    });

    $httpSocket = new React\Socket\SocketServer('0.0.0.0:' . $httpPort, [], $loop);
    $http->listen($httpSocket);
    echo "HTTP push endpoint listening on port {$httpPort} (POST /push)\n";
} catch (\Throwable $e) {
    echo "HTTP push endpoint unavailable (react/http missing): {$e->getMessage()}\n";
    echo "You can install react/http via: composer require react/http\n";
}

$loop->run();
