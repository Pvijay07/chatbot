<?php

namespace App\Models;

use CodeIgniter\Model;

class InsuranceDocumentChunkModel extends Model
{
    protected $table = 'insurance_document_chunks';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = false;
    protected $allowedFields = [
        'document_id',
        'chunk_index',
        'language',
        'content',
        'token_count',
        'keywords',
        'created_at',
    ];
}
