<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        return view('petsfolio', [
            'apiBase'       => site_url('api'),
            'defaultLocale' => $this->request->getLocale() ?: 'en',
            'viewerUserId'  => (int) (($this->currentUser()['id'] ?? 0)),
        ]);
    }
}
