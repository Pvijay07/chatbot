<?php

use App\Services\JwtService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Petsfolio;

final class JwtServiceTest extends CIUnitTestCase
{
    public function testEncodesAndDecodesPayload(): void
    {
        $config = new Petsfolio();
        $config->jwtSecret = 'unit-test-secret';
        $config->jwtIssuer = 'unit-test';
        $config->jwtTtl = 60;

        $service = new JwtService($config);
        $token = $service->encode(['sub' => 7, 'role' => 'admin']);
        $payload = $service->decode($token);

        $this->assertSame(7, $payload['sub']);
        $this->assertSame('admin', $payload['role']);
        $this->assertSame('unit-test', $payload['iss']);
    }
}
