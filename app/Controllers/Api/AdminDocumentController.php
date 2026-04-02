<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\InsuranceDocumentModel;

class AdminDocumentController extends BaseController
{
    private InsuranceDocumentModel $documentModel;

    public function __construct()
    {
        $this->documentModel = new InsuranceDocumentModel();
    }

    public function index()
    {
        return $this->respond([
            'status' => true,
            'data'   => $this->documentModel->orderBy('id', 'DESC')->findAll(),
        ]);
    }

    public function upload()
    {
        $user = $this->currentUser();
        $file = $this->request->getFile('file');
        $language = (string) ($this->request->getPost('language') ?: 'en');

        if ($file === null) {
            return $this->respond([
                'status'  => false,
                'message' => 'No document file was uploaded.',
            ], 422);
        }

        try {
            $result = service('document')->storeUploadedDocument($file, (int) $user['id'], $language);

            return $this->respond([
                'status' => true,
                'data'   => [
                    'document'     => $result,
                    'text_preview' => mb_substr($result['text'], 0, 800),
                ],
            ], 201);
        } catch (\Throwable $exception) {
            log_message('error', 'Petsfolio document upload failed: ' . $exception->getMessage());

            return $this->respond([
                'status'  => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
