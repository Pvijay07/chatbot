<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ChatModel;
use App\Models\MessageModel;

class ChatController extends BaseController
{
    private ChatModel $chatModel;
    private MessageModel $messageModel;

    public function __construct()
    {
        $this->chatModel = new ChatModel();
        $this->messageModel = new MessageModel();
    }

    public function index()
    {
        $user = $this->currentUser();
        $chats = $this->chatModel->getUserChats((int) $user['id']);
        $chatIds = array_map(static fn(array $chat): int => (int) $chat['id'], $chats);
        $lastMessages = $this->messageModel->getLastMessagesForChats($chatIds);

        foreach ($chats as &$chat) {
            $chat['last_message'] = $lastMessages[(int) $chat['id']] ?? '';
        }

        return $this->respond([
            'status' => true,
            'data'   => $chats,
        ]);
    }

    public function create()
    {
        $user = $this->currentUser();
        $payload = $this->payload();
        $title = trim((string) ($payload['title'] ?? 'New chat'));

        $chatId = $this->chatModel->insert([
            'user_id'         => (int) $user['id'],
            'title'           => $title === '' ? 'New chat' : $title,
            'pet_type'        => null,
            'last_message_at' => date('Y-m-d H:i:s'),
        ], true);

        return $this->respond([
            'status' => true,
            'data'   => $this->chatModel->find((int) $chatId),
        ], 201);
    }

    public function show(int $chatId)
    {
        $user = $this->currentUser();
        $chat = $this->chatModel->where('id', $chatId)->where('user_id', $user['id'])->first();

        if ($chat === null) {
            return $this->respond([
                'status'  => false,
                'message' => 'Chat not found.',
            ], 404);
        }

        $messages = $this->messageModel->getChatMessages($chatId);
        foreach ($messages as &$message) {
            $message['sources'] = !empty($message['sources_json']) ? json_decode($message['sources_json'], true) : [];
        }

        return $this->respond([
            'status' => true,
            'data'   => [
                'chat'     => $chat,
                'messages' => $messages,
            ],
        ]);
    }

    public function chat()
    {
        $user = $this->currentUser();
        $payload = $this->payload();
        $locale = $this->setRuntimeLocale($payload['locale'] ?? ($user['preferred_locale'] ?? 'en'));
        $message = trim((string) ($payload['message'] ?? ''));
        $chatId = isset($payload['chat_id']) ? (int) $payload['chat_id'] : null;

        if ($message === '') {
            return $this->respond([
                'status'  => false,
                'message' => 'Message is required.',
            ], 422);
        }

        try {
            $lookupUserId = $this->lookupUserIdFromPayload($payload);
            $result = service('assistant')->handleChat((int) $user['id'], $message, $chatId, $locale, $lookupUserId);

            return $this->respond([
                'status' => true,
                'data'   => $result,
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->respond([
                'status'  => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            log_message('error', 'Petsfolio chat failed: ' . $exception->getMessage());

            return $this->respond([
                'status'  => false,
                'message' => 'The assistant could not process the request.',
            ], 500);
        }
    }

    public function stream()
    {
        $user = $this->currentUser();
        $payload = $this->payload();
        $locale = $this->setRuntimeLocale($payload['locale'] ?? ($user['preferred_locale'] ?? 'en'));
        $message = trim((string) ($payload['message'] ?? ''));
        $chatId = isset($payload['chat_id']) ? (int) $payload['chat_id'] : null;
        $requestId = trim((string) ($payload['request_id'] ?? ''));
        $requestId = $requestId !== '' ? $requestId : uniqid('petsfolio_', true);

        if ($message === '') {
            return $this->respond([
                'status'  => false,
                'message' => 'Message is required.',
            ], 422);
        }

        try {
            $lookupUserId = $this->lookupUserIdFromPayload($payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->respond([
                'status'  => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $this->beginSseResponse();
        $this->emitSse('ready', ['id' => $requestId]);

        try {
            $result = service('assistant')->handleChatStream(
                (int) $user['id'],
                $message,
                function (string $chunk) use ($requestId): void {
                    $this->emitSse('partial', [
                        'id'   => $requestId,
                        'text' => $chunk,
                    ]);
                },
                $chatId,
                $locale,
                $lookupUserId
            );

            $this->emitSse('done', [
                'id'   => $requestId,
                'chat' => $result['chat'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            log_message('error', 'Petsfolio chat stream failed: ' . $exception->getMessage());
            $this->emitSse('error', [
                'id'      => $requestId,
                'message' => 'The assistant could not process the request.',
            ]);
        }

        exit;
    }

    private function lookupUserIdFromPayload(array $payload): ?int
    {
        if (!array_key_exists('user_id', $payload) || $payload['user_id'] === null || $payload['user_id'] === '') {
            return null;
        }

        if (!is_numeric($payload['user_id']) || (int) $payload['user_id'] <= 0) {
            throw new \InvalidArgumentException('user_id must be a positive integer.');
        }

        return (int) $payload['user_id'];
    }

    private function beginSseResponse(): void
    {
        ignore_user_abort(true);
        set_time_limit(0);

        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        @ini_set('output_buffering', 'off');

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate, private');
        header('Pragma: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ':' . str_repeat(' ', 2048) . "\n\n";
        $this->flushStream();
    }

    private function emitSse(string $event, array $payload): void
    {
        echo 'event: ' . $event . "\n";
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        $this->flushStream();
    }

    private function flushStream(): void
    {
        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    }
}
