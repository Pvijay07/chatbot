<?php namespace App\Controllers;

use CodeIgniter\Controller;

class Push extends Controller
{
    /**
     * HTTP endpoint to push a JSON payload into the WebSocket server.
     * Accepts JSON body and forwards it to the local ws-server POST /push.
     */
    public function index()
    {
        // Only allow POST
        if ($this->request->getMethod() !== 'post') {
            return $this->response->setStatusCode(405)->setBody('Method Not Allowed');
        }

        $payload = $this->request->getBody();
        if (empty($payload)) {
            return $this->response->setStatusCode(400)->setBody('Empty payload');
        }

        // pushUri can be configured via env PUSH_ENDPOINT, default to http://127.0.0.1:8081/push
        $pushUri = getenv('PUSH_ENDPOINT') ?: 'http://127.0.0.1:8081/push';

        $client = \Config\Services::curlrequest([
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 30,
        ]);

        try {
            $res = $client->post($pushUri, ['body' => $payload]);
            $code = $res->getStatusCode();
            $body = (string) $res->getBody();
            return $this->response->setStatusCode($code)->setBody($body);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
        }
    }
}
