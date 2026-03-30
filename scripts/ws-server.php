<?php
// Simple Ratchet WebSocket server for broadcasting chat messages.
// Requires: cboden/ratchet and react/socket via composer.

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

require __DIR__ . '/../vendor/autoload.php';

class BroadcastServer implements MessageComponentInterface
{
    protected $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        echo "WebSocket server started\n";
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $conn->history = []; // Initialize history for this connection
        echo "New connection ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        // Expect JSON messages. Two supported formats:
        // {"type":"broadcast","message":"..."} - broadcast to others
        // {"type":"ask","prompt":"..."} - ask LLM and stream tokens back
        $data = json_decode($msg, true) ?: [];

        $type = $data['type'] ?? null;
        if ($type === 'ask' && !empty($data['prompt'])) {
            $prompt = $data['prompt'];
            $id = $data['id'] ?? null;
            $locale = $data['locale'] ?? 'en';

            // Simple local handling for short greetings to avoid off-topic LLM answers
            $trimmed = trim(mb_substr($prompt, 0, 64));
            if (preg_match('/^\s*(hi|hello|hey|hola|hallo|yo|howdy)[\!\.]*$/i', $trimmed) && mb_strlen($trimmed) < 12) {
                $greetings = [
                    'en' => 'Hi — I can help with pet insurance questions. How can I assist you?',
                    'es' => 'Hola — Puedo ayudar con preguntas sobre seguros para mascotas. ¿En qué puedo ayudarte?',
                    'de' => 'Hallo — Ich kann bei Fragen zur Tierkrankenversicherung helfen. Wie kann ich Ihnen helfen?',
                    'fr' => 'Salut — Je peux aider avec des questions sur l\'assurance pour animaux. Comment puis-je vous aider?',
                    'te' => 'హాయ్ — నేను పెట్ ఇన్సూరెన్స్ సంబంధమైన ప్రశ్నలలో సహాయం చేయగలను. నేను ఎలా సహాయపడగలను?',
                    'pt' => 'Oi — Posso ajudar com perguntas sobre seguro para animais de estimação. Como posso ajudar?'
                ];
                $reply = $greetings[$locale] ?? $greetings['en'];

                // Add user message to history
                $from->history[] = ['role' => 'user', 'content' => $prompt];

                // Send reply and done
                $payload = ['type' => 'partial', 'text' => $reply];
                if ($id) $payload['id'] = $id;
                try { $from->send(json_encode($payload)); } catch (\Exception $_) {}

                $from->history[] = ['role' => 'assistant', 'content' => $reply];
                try { $from->send(json_encode(['type' => 'done'] + ($id ? ['id' => $id] : []))); } catch (\Exception $_) {}
                return;
            }

            // Add user message to history
            $from->history[] = ['role' => 'user', 'content' => $prompt];

            // Keep history lean (optional: last 10 messages)
            if (count($from->history) > 20) {
                array_shift($from->history);
                array_shift($from->history);
            }

            $this->streamLlmResponse($from, $from->history, $id, $locale);
            return;
        }

        // fallback: broadcast string or message field
        $text = is_array($data) && isset($data['message']) ? $data['message'] : $msg;

        foreach ($this->clients as $client) {
            if ($from !== $client) {
                $client->send(json_encode(['type' => 'broadcast', 'message' => $text]));
            }
        }
    }

    /**
     * Broadcast raw payload to all connected clients.
     * Accepts string or array.
     */
    public function broadcast($payload)
    {
        $msg = is_string($payload) ? $payload : json_encode($payload);
        foreach ($this->clients as $client) {
            try {
                $client->send($msg);
            } catch (\Exception $_) {
            }
        }
    }

    protected function streamLlmResponse(ConnectionInterface $client, array $history, $id = null, $locale = 'en')
    {
        // Use application LlmService to handle streaming logic.
        try {
            $svc = new \App\Services\LlmService();
            $fullResponse = "";

            $svc->askStream(
                $history,
                $locale,
                function ($chunk) use ($client, $id, &$fullResponse) {
                    $fullResponse .= $chunk;
                    $payload = ['type' => 'partial', 'text' => $chunk];
                    if ($id) $payload['id'] = $id;
                    try {
                        $client->send(json_encode($payload));
                    } catch (\Exception $_) {
                    }
                },
                function ($err = null) use ($client, $id, &$fullResponse) {
                    // When done, save assistant response to history
                    if (!$err && !empty($fullResponse)) {
                        $client->history[] = ['role' => 'assistant', 'content' => $fullResponse];
                    }

                    if ($err) {
                        try {
                            $client->send(json_encode(['type' => 'error', 'message' => $err, 'id' => $id]));
                        } catch (\Exception $_) {
                        }
                    }
                    try {
                        $done = ['type' => 'done'];
                        if ($id) $done['id'] = $id;
                        $client->send(json_encode($done));
                    } catch (\Exception $_) {
                    }
                }
            );
        } catch (\Throwable $e) {
            try {
                $client->send(json_encode(['type' => 'error', 'message' => $e->getMessage(), 'id' => $id]));
            } catch (\Exception $_) {
            }
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
}

// Bootstrap Ratchet WebSocket server and a small HTTP push API using ReactPHP
// WebSocket: 8080, HTTP push endpoint: 8081 (/push)
$loop = React\EventLoop\Factory::create();

$wsPort = 8080;
$httpPort = 8081;

$broadcast = new BroadcastServer();

// WebSocket server socket
$wsSocket = new React\Socket\SocketServer('0.0.0.0:' . $wsPort, [], $loop);
$wsServer = new Ratchet\Server\IoServer(
    new Ratchet\Http\HttpServer(
        new Ratchet\WebSocket\WsServer($broadcast)
    ),
    $wsSocket,
    $loop
);

echo "WebSocket listening on port {$wsPort}\n";

// HTTP API to push messages into WebSocket clients
// Requires react/http (composer require react/http)
try {
    $http = new React\Http\Server(function (Psr\Http\Message\ServerRequestInterface $request) use ($broadcast) {
        $path = $request->getUri()->getPath();
        if ($request->getMethod() !== 'POST' || $path !== '/push') {
            return new React\Http\Message\Response(404, ['Content-Type' => 'text/plain'], "Not found");
        }

        $body = (string) $request->getBody();
        $data = json_decode($body, true);
        // If JSON present and contains an envelope, allow passing it through; otherwise send as message
        if (is_array($data)) {
            $payload = $data;
        } else {
            $payload = ['type' => 'broadcast', 'message' => $body];
        }

        $broadcast->broadcast($payload);

        return new React\Http\Message\Response(200, ['Content-Type' => 'application/json'], json_encode(['ok' => true]));
    });

    $httpSocket = new React\Socket\SocketServer('0.0.0.0:' . $httpPort, [], $loop);
    $http->listen($httpSocket);
    echo "HTTP push endpoint listening on port {$httpPort} (POST /push)\n";
} catch (Throwable $e) {
    echo "HTTP push endpoint unavailable (react/http missing): {$e->getMessage()}\n";
    echo "You can install react/http via: composer require react/http\n";
}

$loop->run();
