<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\InsurancePlanModel;

class PlanController extends BaseController
{
    public function index()
    {
        $petType = $this->request->getGet('pet_type');
        $petType = in_array($petType, ['dog', 'cat'], true) ? $petType : null;

        return $this->respond([
            'status' => true,
            'data'   => (new InsurancePlanModel())->activePlans($petType),
        ]);
    }
}
