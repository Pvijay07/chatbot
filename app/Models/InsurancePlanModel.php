<?php

namespace App\Models;

use CodeIgniter\Model;

class InsurancePlanModel extends Model
{
    protected $table = 'insurance_plans';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'pet_type',
        'slug',
        'name_en',
        'name_hi',
        'summary_en',
        'summary_hi',
        'price_monthly',
        'annual_limit',
        'deductible',
        'reimbursement_percent',
        'waiting_period_days',
        'claim_steps_en',
        'claim_steps_hi',
        'exclusions_en',
        'exclusions_hi',
        'is_active',
    ];

    public function activePlans(?string $petType = null): array
    {
        $builder = $this->where('is_active', 1);

        if ($petType !== null) {
            $builder->where('pet_type', $petType);
        }

        return $builder->orderBy('price_monthly', 'ASC')->findAll();
    }
}
