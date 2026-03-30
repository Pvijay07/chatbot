<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

class ChatController extends BaseController
{
    public function chat()
    {
        $message = trim($this->request->getPost('message'));

        if (empty($message)) {
            return $this->respond(['status' => false, 'message' => 'Message is required'], 400);
        }

        $llm = service('llm');

        $prompt = "User: $message\nAssistant:";

        $reply = $llm->askStream($prompt);

        return $this->respond(['status' => true, 'reply' => $reply]);
    }
}
