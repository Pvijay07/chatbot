<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');

        if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return service('response')->setStatusCode(401)->setJSON([
                'status'  => false,
                'message' => 'Missing bearer token.',
            ]);
        }

        try {
            $payload = service('jwt')->decode(trim($matches[1]));
        } catch (\Throwable $exception) {
            return service('response')->setStatusCode(401)->setJSON([
                'status'  => false,
                'message' => 'Invalid or expired token.',
            ]);
        }

        $user = (new UserModel())->find((int) ($payload['sub'] ?? 0));

        if ($user === null) {
            return service('response')->setStatusCode(401)->setJSON([
                'status'  => false,
                'message' => 'Authenticated user was not found.',
            ]);
        }

        service('authContext')->setUser($user);

        if (method_exists($request, 'setLocale') && !empty($user['preferred_locale'])) {
            $request->setLocale($user['preferred_locale']);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
