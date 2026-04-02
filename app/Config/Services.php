<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */

    public static function authContext($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('authContext');
        }

        return new \App\Libraries\AuthContext();
    }

    public static function jwt($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('jwt');
        }

        return new \App\Services\JwtService(config('Petsfolio'));
    }

    public static function llm($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('llm');
        }

        return new \App\Services\LlmService(config('Petsfolio'));
    }

    public static function retrieval($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('retrieval');
        }

        return new \App\Services\RetrievalService(config('Petsfolio'));
    }

    public static function recommendation($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('recommendation');
        }

        return new \App\Services\RecommendationService();
    }

    public static function assistant($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('assistant');
        }

        return new \App\Services\AssistantService(config('Petsfolio'));
    }

    public static function userInsuranceLookup($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('userInsuranceLookup');
        }

        return new \App\Services\UserInsuranceLookupService();
    }

    public static function document($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('document');
        }

        return new \App\Services\DocumentService();
    }

    public static function docparser($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('docparser');
        }

        return new \App\Services\DocParserService();
    }

    public static function compare($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('compare');
        }

        return new \App\Services\CompareService();
    }
}
