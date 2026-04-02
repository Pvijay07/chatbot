<?php

namespace App\Libraries;

class AuthContext
{
    private ?array $user = null;

    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    public function user(): ?array
    {
        return $this->user;
    }

    public function id(): ?int
    {
        return isset($this->user['id']) ? (int) $this->user['id'] : null;
    }
}
