<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = service('authContext')->user();

        if ($user === null || ($user['role'] ?? 'user') !== 'admin') {
            return service('response')->setStatusCode(403)->setJSON([
                'status'  => false,
                'message' => 'Admin access is required.',
            ]);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
