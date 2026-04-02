<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\InsurancePlanModel;

class AdminPlanController extends BaseController
{
    private InsurancePlanModel $planModel;

    public function __construct()
    {
        $this->planModel = new InsurancePlanModel();
    }

    public function index()
    {
        return $this->respond([
            'status' => true,
            'data'   => $this->planModel->orderBy('pet_type', 'ASC')->orderBy('price_monthly', 'ASC')->findAll(),
        ]);
    }

    public function create()
    {
        try {
            $id = $this->planModel->insert($this->normalizePayload($this->payload()), true);
        } catch (\Throwable $exception) {
            return $this->respond(['status' => false, 'message' => $exception->getMessage()], 422);
        }

        return $this->respond([
            'status' => true,
            'data'   => $this->planModel->find((int) $id),
        ], 201);
    }

    public function update(int $id)
    {
        $plan = $this->planModel->find($id);
        if ($plan === null) {
            return $this->respond(['status' => false, 'message' => 'Plan not found.'], 404);
        }

        try {
            $this->planModel->update($id, $this->normalizePayload($this->payload(), false));
        } catch (\Throwable $exception) {
            return $this->respond(['status' => false, 'message' => $exception->getMessage()], 422);
        }

        return $this->respond([
            'status' => true,
            'data'   => $this->planModel->find($id),
        ]);
    }

    public function delete(int $id)
    {
        $plan = $this->planModel->find($id);
        if ($plan === null) {
            return $this->respond(['status' => false, 'message' => 'Plan not found.'], 404);
        }

        $this->planModel->delete($id);

        return $this->respond([
            'status'  => true,
            'message' => 'Plan deleted.',
        ]);
    }

    private function normalizePayload(array $payload, bool $requireAll = true): array
    {
        $petType = (string) ($payload['pet_type'] ?? '');
        if ($requireAll && !in_array($petType, ['dog', 'cat'], true)) {
            throw new \InvalidArgumentException('pet_type must be dog or cat.');
        }

        return array_filter([
            'pet_type'              => in_array($petType, ['dog', 'cat'], true) ? $petType : null,
            'slug'                  => isset($payload['slug']) ? strtolower(trim((string) $payload['slug'])) : null,
            'name_en'               => isset($payload['name_en']) ? trim((string) $payload['name_en']) : null,
            'name_hi'               => isset($payload['name_hi']) ? trim((string) $payload['name_hi']) : null,
            'summary_en'            => isset($payload['summary_en']) ? trim((string) $payload['summary_en']) : null,
            'summary_hi'            => isset($payload['summary_hi']) ? trim((string) $payload['summary_hi']) : null,
            'price_monthly'         => isset($payload['price_monthly']) ? (float) $payload['price_monthly'] : null,
            'annual_limit'          => isset($payload['annual_limit']) ? (int) $payload['annual_limit'] : null,
            'deductible'            => isset($payload['deductible']) ? (int) $payload['deductible'] : null,
            'reimbursement_percent' => isset($payload['reimbursement_percent']) ? (int) $payload['reimbursement_percent'] : null,
            'waiting_period_days'   => isset($payload['waiting_period_days']) ? (int) $payload['waiting_period_days'] : null,
            'claim_steps_en'        => isset($payload['claim_steps_en']) ? trim((string) $payload['claim_steps_en']) : null,
            'claim_steps_hi'        => isset($payload['claim_steps_hi']) ? trim((string) $payload['claim_steps_hi']) : null,
            'exclusions_en'         => isset($payload['exclusions_en']) ? trim((string) $payload['exclusions_en']) : null,
            'exclusions_hi'         => isset($payload['exclusions_hi']) ? trim((string) $payload['exclusions_hi']) : null,
            'is_active'             => isset($payload['is_active']) ? (int) (bool) $payload['is_active'] : null,
        ], static fn($value) => $value !== null);
    }
}
