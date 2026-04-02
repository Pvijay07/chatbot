<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    use ResponseTrait;
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');

        // Apply locale from session so `lang()` uses the user's selected language.
        try {
            $session = session();
            $locale = $session->get('locale') ?? null;
            if (!empty($locale) && method_exists($this->request, 'setLocale')) {
                $this->request->setLocale($locale);
            }
        } catch (\Throwable $_) {
            // ignore session/locale errors and continue
        }
    }

    protected function payload(): array
    {
        try {
            $json = $this->request->getJSON(true);
            if (is_array($json)) {
                return $json;
            }
        } catch (\Throwable $_) {
            // Fall back to raw input and form data if the JSON body is malformed.
        }

        $raw = $this->request->getRawInput();
        if (is_array($raw) && $raw !== []) {
            return $raw;
        }

        return $this->request->getPost() ?: [];
    }

    protected function setRuntimeLocale(?string $fallback = null): string
    {
        $payload = $this->payload();
        $locale = $payload['locale']
            ?? $this->request->getHeaderLine('X-Locale')
            ?? $fallback
            ?? $this->request->getLocale()
            ?? 'en';

        $supported = config('Petsfolio')->supportedLocales;
        if (!in_array($locale, $supported, true)) {
            $locale = 'en';
        }

        if (method_exists($this->request, 'setLocale')) {
            $this->request->setLocale($locale);
        }

        try {
            session()->set('locale', $locale);
        } catch (\Throwable $_) {
            // ignore session/locale errors and continue
        }

        return $locale;
    }

    protected function currentUser(): ?array
    {
        $user = service('authContext')->user();
        if ($user !== null) {
            return $user;
        }

        try {
            $session = session();
            $guestId = $session->get('petsfolio_guest_id');

            if (!is_int($guestId) || $guestId <= 0) {
                $seed = session_id();
                $guestId = (int) sprintf('%u', crc32($seed !== '' ? $seed : uniqid('petsfolio', true)));

                if ($guestId <= 0) {
                    $guestId = random_int(1, PHP_INT_MAX);
                }

                $session->set('petsfolio_guest_id', $guestId);
            }

            return [
                'id'               => $guestId,
                'name'             => 'Petsfolio Guest',
                'email'            => null,
                'role'             => 'user',
                'preferred_locale' => $session->get('locale') ?? $this->request->getLocale() ?? 'en',
            ];
        } catch (\Throwable $_) {
            return [
                'id'               => 1,
                'name'             => 'Petsfolio Guest',
                'email'            => null,
                'role'             => 'user',
                'preferred_locale' => $this->request->getLocale() ?? 'en',
            ];
        }
    }
}
