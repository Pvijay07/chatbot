<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;

class AuthController extends BaseController
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function register()
    {
        $payload = $this->payload();
        $locale = $this->setRuntimeLocale($payload['preferred_locale'] ?? null);
        $name = trim((string) ($payload['name'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            return $this->respond([
                'status'  => false,
                'message' => 'Name, email, and password are required.',
            ], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->respond([
                'status'  => false,
                'message' => 'A valid email address is required.',
            ], 422);
        }

        if (strlen($password) < 8) {
            return $this->respond([
                'status'  => false,
                'message' => 'Password must be at least 8 characters long.',
            ], 422);
        }

        if ($this->userModel->findByEmail($email) !== null) {
            return $this->respond([
                'status'  => false,
                'message' => 'An account with that email already exists.',
            ], 409);
        }

        $userId = $this->userModel->insert([
            'name'             => $name,
            'email'            => $email,
            'password_hash'    => password_hash($password, PASSWORD_DEFAULT),
            'role'             => 'user',
            'preferred_locale' => $locale,
        ], true);

        $user = $this->userModel->find((int) $userId);

        return $this->respond([
            'status' => true,
            'data'   => [
                'token' => service('jwt')->encode([
                    'sub'  => (int) $user['id'],
                    'role' => $user['role'],
                ]),
                'user'  => $this->safeUser($user),
            ],
        ], 201);
    }

    public function login()
    {
        $payload = $this->payload();
        $locale = $this->setRuntimeLocale($payload['preferred_locale'] ?? null);
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');

        $user = $this->userModel->findByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return $this->respond([
                'status'  => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if ($locale !== ($user['preferred_locale'] ?? 'en')) {
            $this->userModel->update((int) $user['id'], ['preferred_locale' => $locale]);
            $user['preferred_locale'] = $locale;
        }

        return $this->respond([
            'status' => true,
            'data'   => [
                'token' => service('jwt')->encode([
                    'sub'  => (int) $user['id'],
                    'role' => $user['role'],
                ]),
                'user'  => $this->safeUser($user),
            ],
        ]);
    }

    public function me()
    {
        $user = $this->currentUser();

        return $this->respond([
            'status' => true,
            'data'   => $this->safeUser($user),
        ]);
    }

    private function safeUser(?array $user): array
    {
        return [
            'id'               => (int) ($user['id'] ?? 0),
            'name'             => (string) ($user['name'] ?? ''),
            'email'            => (string) ($user['email'] ?? ''),
            'role'             => (string) ($user['role'] ?? 'user'),
            'preferred_locale' => (string) ($user['preferred_locale'] ?? 'en'),
        ];
    }
}
