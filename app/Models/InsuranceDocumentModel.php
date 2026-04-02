<?php

namespace App\Models;

use CodeIgniter\Model;

class InsuranceDocumentModel extends Model
{
    protected $table = 'insurance_documents';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'title',
        'file_name',
        'file_path',
        'mime_type',
        'language',
        'content_hash',
        'uploaded_by',
        'is_active',
    ];
}
