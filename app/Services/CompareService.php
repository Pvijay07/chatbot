<?php

namespace App\Services;

class CompareService
{
    public function compare($system, $user)
    {
        $result = [
            'missing' => [],
            'valid' => [],
            'mismatch' => []
        ];

        // Pet name
        if (($system['pet_name'] ?? '') === ($user['pet_name'] ?? '')) {
            $result['valid'][] = 'Pet name matched';
        } else {
            $result['mismatch'][] = 'Pet name mismatch';
        }

        $sysVaccines = array_column($system['vaccines'] ?? [], 'name');
        $usrVaccines = array_column($user['vaccines'] ?? [], 'name');

        foreach ($sysVaccines as $v) {
            if (!in_array($v, $usrVaccines)) {
                $result['missing'][] = "$v vaccine missing";
            }
        }

        return $result;
    }
}