<?php

namespace App\Services;

use Config\Petsfolio;

class JwtService
{
    public function __construct(private readonly Petsfolio $config = new Petsfolio())
    {
    }

    public function encode(array $claims): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now = time();

        $payload = array_merge([
            'iss' => $this->config->jwtIssuer,
            'iat' => $now,
            'exp' => $now + $this->config->jwtTtl,
        ], $claims);

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $this->config->jwtSecret, true);

        return $encodedHeader . '.' . $encodedPayload . '.' . $this->base64UrlEncode($signature);
    }

    public function decode(string $token): array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw new \RuntimeException('Malformed token.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;

        $expected = $this->base64UrlEncode(hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $this->config->jwtSecret, true));
        if (!hash_equals($expected, $encodedSignature)) {
            throw new \RuntimeException('Token signature mismatch.');
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true, 512, JSON_THROW_ON_ERROR);

        if (($payload['iss'] ?? null) !== $this->config->jwtIssuer) {
            throw new \RuntimeException('Token issuer mismatch.');
        }

        if (($payload['exp'] ?? 0) < time()) {
            throw new \RuntimeException('Token expired.');
        }

        return $payload;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }
}
