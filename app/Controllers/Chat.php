<?php namespace App\Controllers;

use CodeIgniter\Controller;

class Chat extends Controller
{
    public function index()
    {
        $session = session();
        $data = [];
        // current locale
        $data['locale'] = $session->get('locale') ?? 'en';

        echo view('chat', $data);
    }

    public function setLanguage()
    {
        $locale = $this->request->getPost('locale');
        if (!empty($locale)) {
            session()->set('locale', $locale);
        }
        // Ensure redirect targets the public front controller in this dev setup
        return redirect()->to(base_url('public/index.php/chat'));
    }
}
