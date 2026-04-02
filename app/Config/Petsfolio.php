<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Petsfolio extends BaseConfig
{
    public string $jwtSecret;
    public string $jwtIssuer;
    public int $jwtTtl;
    public string $llmBaseUrl;
    public string $llmModel;
    public bool $llmEnabled;
    public int $llmTimeout;
    public int $retrievalChunkSize;
    public int $retrievalChunkOverlap;
    public int $retrievalResultLimit;
    public int $contextMessageLimit;
    public int $cacheTtl;

    /**
     * @var list<string>
     */
    public array $supportedLocales = ['en', 'hi', 'te', 'kn', 'ta'];

    public function __construct()
    {
        parent::__construct();

        $this->jwtSecret = (string) env('petsfolio.jwtSecret', 'petsfolio-local-dev-secret-change-me');
        $this->jwtIssuer = (string) env('petsfolio.jwtIssuer', 'petsfolio-insurance-assistant');
        $this->jwtTtl = (int) env('petsfolio.jwtTtl', 60 * 60 * 24 * 7);
        $this->llmBaseUrl = rtrim((string) env('petsfolio.llmBaseUrl', 'http://127.0.0.1:11434'), '/');
        $this->llmModel = (string) env('petsfolio.llmModel', 'llama3.1:8b');
        $this->llmEnabled = filter_var(env('petsfolio.llmEnabled', true), FILTER_VALIDATE_BOOLEAN);
        $this->llmTimeout = (int) env('petsfolio.llmTimeout', 120);
        $this->retrievalChunkSize = (int) env('petsfolio.retrievalChunkSize', 750);
        $this->retrievalChunkOverlap = (int) env('petsfolio.retrievalChunkOverlap', 120);
        $this->retrievalResultLimit = (int) env('petsfolio.retrievalResultLimit', 4);
        $this->contextMessageLimit = (int) env('petsfolio.contextMessageLimit', 5);
        $this->cacheTtl = (int) env('petsfolio.cacheTtl', 300);
    }
}
