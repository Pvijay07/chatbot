<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

class UserInsuranceLookupService
{
    public function __construct(private ?BaseConnection $db = null)
    {
    }

    public function replyForMessage(string $message, ?int $inputUserId, int $fallbackUserId, array $history = [], string $locale = 'en'): ?array
    {
        if (!$this->shouldHandle($message, $inputUserId, $history)) {
            return null;
        }

        $targetUserId = $this->resolveTargetUserId($message, $inputUserId, $fallbackUserId, $history);
        if ($targetUserId === null) {
            return [
                'mode'    => 'snapshot',
                'reply'   => $this->missingUserIdMessage($locale),
                'sources' => [],
                'context_blocks' => [],
            ];
        }

        try {
            $mode = $this->isListIntent($message, $inputUserId) ? 'snapshot' : 'qa';
            $sections = $mode === 'snapshot'
                ? $this->requestedSections($message)
                : ['pets', 'policies', 'claims'];

            $pets = in_array('pets', $sections, true) ? $this->fetchPets($targetUserId) : [];
            $policies = in_array('policies', $sections, true) ? $this->fetchPolicies($targetUserId) : [];
            $claims = in_array('claims', $sections, true) ? $this->fetchClaims($targetUserId) : [];
        } catch (\Throwable $exception) {
            log_message('error', 'User insurance lookup failed: ' . $exception->getMessage());

            return [
                'mode'    => $mode ?? 'snapshot',
                'reply'   => $this->databaseErrorMessage($locale),
                'sources' => [],
                'context_blocks' => [],
            ];
        }

        $reply = $mode === 'snapshot'
            ? $this->buildReply($targetUserId, $sections, $pets, $policies, $claims, $locale)
            : $this->answerFromRecords($message, $targetUserId, $pets, $policies, $claims, $locale);

        return [
            'mode'           => $mode,
            'reply'          => $reply,
            'sources'        => [],
            'context_blocks' => [],
        ];
    }

    public function shouldHandle(string $message, ?int $inputUserId = null, array $history = []): bool
    {
        $message = trim($message);
        if ($message === '') {
            return false;
        }

        if ($inputUserId !== null && $inputUserId > 0) {
            return true;
        }

        if ($this->isListIntent($message)) {
            return true;
        }

        return $this->extractContextUserIdFromHistory($history) !== null
            && $this->looksLikeRecordFollowUp($message);
    }

    public function resolveTargetUserId(string $message, ?int $inputUserId, int $fallbackUserId, array $history = []): ?int
    {
        if ($inputUserId !== null && $inputUserId > 0) {
            return $inputUserId;
        }

        $patterns = [
            '/\buser[_\s-]?id\b\s*[:#=-]?\s*(\d+)/i',
            '/\buid\b\s*[:#=-]?\s*(\d+)/i',
            '/\bfor\s+user\b\s*[:#=-]?\s*(\d+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return (int) $matches[1];
            }
        }

        $contextUserId = $this->extractContextUserIdFromHistory($history);
        if ($contextUserId !== null && $this->looksLikeRecordFollowUp($message)) {
            return $contextUserId;
        }

        if (preg_match('/\b(my|me)\b/i', $message) && $fallbackUserId > 0) {
            return $fallbackUserId;
        }

        return null;
    }

    public function requestedSections(string $message): array
    {
        $sections = [];

        if (preg_match('/\b(pet|pets|dog|cat)\b/i', $message)) {
            $sections[] = 'pets';
        }

        if (preg_match('/\b(policy|policies|insurance)\b/i', $message)) {
            $sections[] = 'policies';
        }

        if (preg_match('/\b(claim|claims)\b/i', $message)) {
            $sections[] = 'claims';
        }

        return $sections === [] ? ['pets', 'policies', 'claims'] : $sections;
    }

    public function buildResponse(int $userId, array $sections, array $pets, array $policies, array $claims, string $locale = 'en'): array
    {
        return [
            'reply'   => $this->buildReply($userId, $sections, $pets, $policies, $claims, $locale),
            'sources' => [],
        ];
    }

    public function extractContextUserIdFromHistory(array $history): ?int
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $entry = $history[$i];
            $text = is_array($entry) ? (string) ($entry['message'] ?? '') : (string) $entry;

            if ($text === '') {
                continue;
            }

            if (preg_match('/\buser[_\s-]?id\b\s*[:#=-]?\s*(\d+)/i', $text, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    public function answerFromRecords(string $message, int $userId, array $pets, array $policies, array $claims, string $locale = 'en'): string
    {
        if ($pets === [] && $policies === [] && $claims === []) {
            return $this->notFoundMessage($userId, $locale);
        }

        $matchedPet = $this->findMatchingPet($message, $pets);
        $matchedPolicy = $this->findMatchingPolicy($message, $policies, $matchedPet);
        $matchedClaim = $this->findMatchingClaim($message, $claims, $matchedPolicy, $matchedPet);

        if ($matchedPet === null && count($pets) === 1 && $this->asksForPetField($message)) {
            $matchedPet = $pets[0];
        }

        if ($matchedPolicy === null && count($policies) === 1 && $this->asksForPolicyField($message)) {
            $matchedPolicy = $policies[0];
        }

        if ($matchedClaim === null && count($claims) === 1 && $this->asksForClaimField($message)) {
            $matchedClaim = $claims[0];
        }

        if ($matchedPet === null && count($pets) > 1 && $this->asksForPetField($message)) {
            return 'Petsfolio found multiple pets for this user. Please mention the pet name so Petsfolio can answer accurately.';
        }

        if ($matchedPolicy === null && count($policies) > 1 && $this->asksForPolicyField($message)) {
            return 'Petsfolio found multiple policies for this user. Please mention the pet name or policy number so Petsfolio can answer accurately.';
        }

        if ($matchedClaim === null && count($claims) > 1 && $this->asksForClaimField($message)) {
            return 'Petsfolio found multiple claims for this user. Please mention the claim number or pet name so Petsfolio can answer accurately.';
        }

        if (preg_match('/\bhow many\b/i', $message)) {
            return sprintf(
                'Petsfolio found %d pet record(s), %d policy record(s), and %d claim record(s) for user_id %d.',
                count($pets),
                count($policies),
                count($claims),
                $userId
            );
        }

        if ($matchedPet !== null && preg_match('/\b(policy|policies|insurance)\b/i', $message)) {
            $petPolicies = array_values(array_filter(
                $policies,
                fn(array $policy): bool => $this->sameText($policy['pet_name'] ?? null, $matchedPet['name'] ?? null)
            ));

            if ($petPolicies === []) {
                return sprintf('Petsfolio could not find any policy for %s.', $this->value($matchedPet['name'] ?? null));
            }

            $lines = [sprintf('Petsfolio found the following policy details for %s:', $this->value($matchedPet['name'] ?? null))];
            foreach ($petPolicies as $policy) {
                $lines[] = $this->policySentence($policy);
            }

            return implode("\n", $lines);
        }

        if ($matchedPet !== null && preg_match('/\b(claim|claims)\b/i', $message)) {
            $petClaims = array_values(array_filter(
                $claims,
                fn(array $claim): bool => $this->sameText($claim['pet_name'] ?? null, $matchedPet['name'] ?? null)
            ));

            if ($petClaims === []) {
                return sprintf('Petsfolio could not find any claim for %s.', $this->value($matchedPet['name'] ?? null));
            }

            $lines = [sprintf('Petsfolio found the following claim details for %s:', $this->value($matchedPet['name'] ?? null))];
            foreach ($petClaims as $claim) {
                $lines[] = $this->claimSentence($claim);
            }

            return implode("\n", $lines);
        }

        if ($matchedPet !== null && $this->asksForBreed($message)) {
            return sprintf(
                'Petsfolio found that %s has breed %s.',
                $this->value($matchedPet['name'] ?? null),
                $this->value($matchedPet['breed'] ?? null, 'not available')
            );
        }

        if ($matchedPet !== null && $this->asksForAge($message)) {
            $age = $this->ageLabel($matchedPet['dob'] ?? null);

            return $age !== null
                ? sprintf('Petsfolio found that %s is %s old.', $this->value($matchedPet['name'] ?? null), $age)
                : sprintf('Petsfolio could not find the age for %s because the date of birth is not available.', $this->value($matchedPet['name'] ?? null));
        }

        if ($matchedPet !== null && $this->asksForName($message)) {
            return sprintf('Petsfolio found the pet name as %s.', $this->value($matchedPet['name'] ?? null));
        }

        if ($matchedPet !== null && $this->asksForGender($message)) {
            return sprintf(
                'Petsfolio found that %s has gender %s.',
                $this->value($matchedPet['name'] ?? null),
                $this->humanizeValue($matchedPet['gender'] ?? null, 'not available')
            );
        }

        if ($matchedPet !== null && $this->asksForType($message)) {
            return sprintf(
                'Petsfolio found that %s is a %s.',
                $this->value($matchedPet['name'] ?? null),
                $this->humanizeValue($matchedPet['type'] ?? null, 'pet')
            );
        }

        if ($matchedPet !== null && $this->asksForInsuredFlag($message)) {
            return sprintf(
                'Petsfolio found that %s is insured: %s.',
                $this->value($matchedPet['name'] ?? null),
                $this->humanizeFlag($matchedPet['insured'] ?? null)
            );
        }

        if ($matchedPet !== null && $this->asksForVaccination($message)) {
            return sprintf(
                'Petsfolio found that %s is vaccinated: %s.',
                $this->value($matchedPet['name'] ?? null),
                $this->humanizeFlag($matchedPet['vaccinated'] ?? null)
            );
        }

        if ($matchedPet !== null && $this->asksForDateOfBirth($message)) {
            return sprintf(
                'Petsfolio found the date of birth for %s as %s.',
                $this->value($matchedPet['name'] ?? null),
                $this->value($matchedPet['dob'] ?? null, 'not available')
            );
        }

        if ($matchedPet !== null && $this->asksForLastVetVisit($message)) {
            return sprintf(
                'Petsfolio found the last vet visit for %s as %s.',
                $this->value($matchedPet['name'] ?? null),
                $this->value($matchedPet['last_vet_visit'] ?? null, 'not available')
            );
        }

        if ($matchedPolicy !== null && $this->asksForPetName($message)) {
            return sprintf(
                'Petsfolio found that policy %s belongs to %s.',
                $this->value($matchedPolicy['policy_number'] ?? null),
                $this->value($matchedPolicy['pet_name'] ?? null, 'the pet')
            );
        }

        if ($matchedPolicy !== null && $this->asksForProvider($message)) {
            return sprintf(
                'Petsfolio found the provider for policy %s as %s.',
                $this->value($matchedPolicy['policy_number'] ?? null),
                $this->value($matchedPolicy['provider'] ?? null, 'not available')
            );
        }

        if ($matchedPolicy !== null && $this->asksForCoverage($message)) {
            return sprintf(
                'Petsfolio found the coverage type for policy %s as %s.',
                $this->value($matchedPolicy['policy_number'] ?? null),
                $this->value($matchedPolicy['coverage_type'] ?? null, 'not available')
            );
        }

        if ($matchedPolicy !== null && $this->asksForStatus($message)) {
            return sprintf(
                'Petsfolio found the status for policy %s as %s.',
                $this->value($matchedPolicy['policy_number'] ?? null),
                $this->value($matchedPolicy['status'] ?? null, 'not available')
            );
        }

        if ($matchedPolicy !== null && $this->asksForPolicyPeriod($message)) {
            return sprintf(
                'Petsfolio found that policy %s runs from %s to %s.',
                $this->value($matchedPolicy['policy_number'] ?? null),
                $this->value($matchedPolicy['start_date'] ?? null, 'not available'),
                $this->value($matchedPolicy['end_date'] ?? null, 'not available')
            );
        }

        if ($matchedPolicy !== null) {
            $lines = ['Petsfolio found this policy:'];
            $lines[] = $this->policySentence($matchedPolicy);

            $policyClaims = array_values(array_filter(
                $claims,
                fn(array $claim): bool => $this->sameText($claim['policy_number'] ?? null, $matchedPolicy['policy_number'] ?? null)
            ));

            if ($policyClaims !== []) {
                $lines[] = 'Petsfolio also found these related claims:';
                foreach ($policyClaims as $claim) {
                    $lines[] = $this->claimSentence($claim);
                }
            }

            return implode("\n", $lines);
        }

        if ($matchedClaim !== null && $this->asksForAmount($message)) {
            return sprintf(
                'Petsfolio found the claim amount for claim #%s as %s.',
                $this->value($matchedClaim['id'] ?? null),
                $this->money($matchedClaim['amount'] ?? null)
            );
        }

        if ($matchedClaim !== null && $this->asksForStatus($message)) {
            return sprintf(
                'Petsfolio found the status for claim #%s as %s.',
                $this->value($matchedClaim['id'] ?? null),
                $this->value($matchedClaim['status'] ?? null, 'not available')
            );
        }

        if ($matchedClaim !== null && $this->asksForIncidentDate($message)) {
            return sprintf(
                'Petsfolio found the incident date for claim #%s as %s.',
                $this->value($matchedClaim['id'] ?? null),
                $this->value($matchedClaim['incident_date'] ?? null, 'not available')
            );
        }

        if ($matchedClaim !== null) {
            return "Petsfolio found this claim:\n" . $this->claimSentence($matchedClaim);
        }

        if ($matchedPet !== null) {
            return "Petsfolio found this pet:\n" . $this->petSentence($matchedPet);
        }

        if (preg_match('/\b(active|pending|approved|rejected|paid|settled|under review|in progress)\b/i', $message, $matches)) {
            $status = strtolower($matches[1]);

            if (preg_match('/\b(policy|policies|insurance)\b/i', $message)) {
                $statusPolicies = array_values(array_filter(
                    $policies,
                    fn(array $policy): bool => strtolower((string) ($policy['status'] ?? '')) === $status
                ));

                if ($statusPolicies === []) {
                    return sprintf('Petsfolio could not find any %s policy.', $status);
                }

                $lines = [sprintf('Petsfolio found these %s policies:', $status)];
                foreach ($statusPolicies as $policy) {
                    $lines[] = $this->policySentence($policy);
                }

                return implode("\n", $lines);
            }

            if (preg_match('/\b(claim|claims)\b/i', $message)) {
                $statusClaims = array_values(array_filter(
                    $claims,
                    fn(array $claim): bool => strtolower((string) ($claim['status'] ?? '')) === $status
                ));

                if ($statusClaims === []) {
                    return sprintf('Petsfolio could not find any %s claim.', $status);
                }

                $lines = [sprintf('Petsfolio found these %s claims:', $status)];
                foreach ($statusClaims as $claim) {
                    $lines[] = $this->claimSentence($claim);
                }

                return implode("\n", $lines);
            }
        }

        return sprintf(
            'Petsfolio found %d pet record(s), %d policy record(s), and %d claim record(s) for user_id %d. You can ask about pet name, breed, age, policy number, provider, coverage, status, or claim amount.',
            count($pets),
            count($policies),
            count($claims),
            $userId
        );
    }

    private function fetchPets(int $userId): array
    {
        return $this->db()
            ->table('user_pets')
            ->select('id, name, type, breed, dob, gender, insured, vaccinated, last_vet_visit')
            ->where('user_id', $userId)
            ->groupStart()
                ->where('is_deleted', 0)
                ->orWhere('is_deleted IS NULL', null, false)
            ->groupEnd()
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function fetchPolicies(int $userId): array
    {
        return $this->db()
            ->table('insurance_policies p')
            ->select('p.id, p.policy_number, p.provider, p.coverage_type, p.status, p.start_date, p.end_date, p.remarks, up.name AS pet_name, up.type AS pet_type')
            ->join('insurance_users iu', 'iu.id = p.insurance_user_id', 'inner')
            ->join('user_pets up', 'up.id = p.pet_id', 'left')
            ->where('iu.user_id', $userId)
            ->orderBy('p.updated_at', 'DESC')
            ->orderBy('p.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function fetchClaims(int $userId): array
    {
        return $this->db()
            ->table('insurance_claims c')
            ->select('c.id, c.claim_type, c.status, c.amount, c.incident_date, c.created_at, p.policy_number, up.name AS pet_name')
            ->join('insurance_policies p', 'p.id = c.policy_id', 'inner')
            ->join('insurance_users iu', 'iu.id = p.insurance_user_id', 'inner')
            ->join('user_pets up', 'up.id = p.pet_id', 'left')
            ->where('iu.user_id', $userId)
            ->orderBy('c.updated_at', 'DESC')
            ->orderBy('c.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function buildReply(int $userId, array $sections, array $pets, array $policies, array $claims, string $locale): string
    {
        $hasData = $pets !== [] || $policies !== [] || $claims !== [];
        if (!$hasData) {
            return $this->notFoundMessage($userId, $locale);
        }

        $lines = [
            $this->headerMessage($userId, $locale),
            '',
        ];

        if (in_array('pets', $sections, true)) {
            $lines[] = sprintf('Pets (%d)', count($pets));
            if ($pets === []) {
                $lines[] = '- Petsfolio could not find any pet records.';
            } else {
                foreach ($pets as $pet) {
                    $lines[] = $this->petSentence($pet);
                }
            }
            $lines[] = '';
        }

        if (in_array('policies', $sections, true)) {
            $lines[] = sprintf('Policies (%d)', count($policies));
            if ($policies === []) {
                $lines[] = '- Petsfolio could not find any policy records.';
            } else {
                foreach ($policies as $policy) {
                    $lines[] = $this->policySentence($policy);
                }
            }
            $lines[] = '';
        }

        if (in_array('claims', $sections, true)) {
            $lines[] = sprintf('Claims (%d)', count($claims));
            if ($claims === []) {
                $lines[] = '- Petsfolio could not find any claim records.';
            } else {
                foreach ($claims as $claim) {
                    $lines[] = $this->claimSentence($claim);
                }
            }
        }

        return trim(implode("\n", $lines));
    }

    private function buildSources(int $userId, array $sections, array $pets, array $policies, array $claims): array
    {
        $sources = [];

        if (in_array('pets', $sections, true) && $pets !== []) {
            $sources[] = [
                'type'    => 'database',
                'title'   => 'Pets from database',
                'snippet' => sprintf('%d pet record(s) found for user_id %d.', count($pets), $userId),
            ];
        }

        if (in_array('policies', $sections, true) && $policies !== []) {
            $sources[] = [
                'type'    => 'database',
                'title'   => 'Policies from database',
                'snippet' => sprintf('%d policy record(s) found for user_id %d.', count($policies), $userId),
            ];
        }

        if (in_array('claims', $sections, true) && $claims !== []) {
            $sources[] = [
                'type'    => 'database',
                'title'   => 'Claims from database',
                'snippet' => sprintf('%d claim record(s) found for user_id %d.', count($claims), $userId),
            ];
        }

        return $sources;
    }

    private function buildContextBlocks(int $userId, array $pets, array $policies, array $claims): array
    {
        $blocks = [];

        if ($pets !== []) {
            $blocks[] = [
                'title'   => 'User Pets',
                'content' => "user_id: {$userId}\n" . implode("\n", array_map(fn(array $pet): string => $this->petLine($pet), $pets)),
            ];
        }

        if ($policies !== []) {
            $blocks[] = [
                'title'   => 'User Policies',
                'content' => "user_id: {$userId}\n" . implode("\n", array_map(fn(array $policy): string => $this->policyLine($policy), $policies)),
            ];
        }

        if ($claims !== []) {
            $blocks[] = [
                'title'   => 'User Claims',
                'content' => "user_id: {$userId}\n" . implode("\n", array_map(fn(array $claim): string => $this->claimLine($claim), $claims)),
            ];
        }

        return $blocks;
    }

    private function db(): BaseConnection
    {
        $this->db ??= Database::connect();

        return $this->db;
    }

    private function isListIntent(string $message, ?int $inputUserId = null): bool
    {
        if ($inputUserId !== null && $inputUserId > 0) {
            return true;
        }

        return (bool) preg_match('/\b(check|show|list|get|find|lookup|look up|my|me|user[_\s-]?id|uid|for user)\b/i', $message);
    }

    private function looksLikeRecordFollowUp(string $message): bool
    {
        return (bool) preg_match('/\b(pet|pets|dog|cat|policy|policies|claim|claims|insured|vaccinated|provider|coverage|status|amount|incident|period|start|end|pending|active|approved|rejected|paid|settled|tell|about|details?|which|what|how many|name|breed|age|old|gender|type|dob|birth|vet)\b/i', $message);
    }

    private function asksForPetField(string $message): bool
    {
        return $this->asksForBreed($message)
            || $this->asksForAge($message)
            || $this->asksForName($message)
            || $this->asksForGender($message)
            || $this->asksForType($message)
            || $this->asksForInsuredFlag($message)
            || $this->asksForVaccination($message)
            || $this->asksForDateOfBirth($message)
            || $this->asksForLastVetVisit($message);
    }

    private function asksForPolicyField(string $message): bool
    {
        return $this->asksForPetName($message)
            || $this->asksForProvider($message)
            || $this->asksForCoverage($message)
            || $this->asksForStatus($message)
            || $this->asksForPolicyPeriod($message)
            || (bool) preg_match('/\b(policy|policies|insurance)\b/i', $message);
    }

    private function asksForClaimField(string $message): bool
    {
        return $this->asksForAmount($message)
            || $this->asksForStatus($message)
            || $this->asksForIncidentDate($message)
            || (bool) preg_match('/\b(claim|claims)\b/i', $message);
    }

    private function asksForBreed(string $message): bool
    {
        return (bool) preg_match('/\bbreed\b/i', $message);
    }

    private function asksForAge(string $message): bool
    {
        return (bool) preg_match('/\b(age|old)\b/i', $message);
    }

    private function asksForName(string $message): bool
    {
        return (bool) preg_match('/\bname\b/i', $message);
    }

    private function asksForPetName(string $message): bool
    {
        return $this->asksForName($message) && (bool) preg_match('/\bpet\b/i', $message);
    }

    private function asksForGender(string $message): bool
    {
        return (bool) preg_match('/\b(gender|male|female)\b/i', $message);
    }

    private function asksForType(string $message): bool
    {
        return (bool) preg_match('/\b(type|species)\b/i', $message);
    }

    private function asksForInsuredFlag(string $message): bool
    {
        return (bool) preg_match('/\binsured\b/i', $message);
    }

    private function asksForVaccination(string $message): bool
    {
        return (bool) preg_match('/\b(vaccinated|vaccination|vaccine|rabies)\b/i', $message);
    }

    private function asksForDateOfBirth(string $message): bool
    {
        return (bool) preg_match('/\b(dob|birth|date of birth)\b/i', $message);
    }

    private function asksForLastVetVisit(string $message): bool
    {
        return (bool) preg_match('/\b(last vet|vet visit|visit)\b/i', $message);
    }

    private function asksForProvider(string $message): bool
    {
        return (bool) preg_match('/\bprovider\b/i', $message);
    }

    private function asksForCoverage(string $message): bool
    {
        return (bool) preg_match('/\b(coverage|cover)\b/i', $message);
    }

    private function asksForStatus(string $message): bool
    {
        return (bool) preg_match('/\b(status|active|pending|approved|rejected|paid|settled|under review|in progress)\b/i', $message);
    }

    private function asksForPolicyPeriod(string $message): bool
    {
        return (bool) preg_match('/\b(period|start|end|from|to|date)\b/i', $message);
    }

    private function asksForAmount(string $message): bool
    {
        return (bool) preg_match('/\b(amount|price|cost)\b/i', $message);
    }

    private function asksForIncidentDate(string $message): bool
    {
        return (bool) preg_match('/\b(incident|date)\b/i', $message);
    }

    private function findMatchingPet(string $message, array $pets): ?array
    {
        $normalizedMessage = $this->normalizeForMatch($message);

        foreach ($pets as $pet) {
            $name = (string) ($pet['name'] ?? '');
            if ($name !== '' && str_contains($normalizedMessage, $this->normalizeForMatch($name))) {
                return $pet;
            }
        }

        return null;
    }

    private function findMatchingPolicy(string $message, array $policies, ?array $matchedPet = null): ?array
    {
        $normalizedMessage = $this->normalizeForMatch($message);

        foreach ($policies as $policy) {
            $policyNumber = (string) ($policy['policy_number'] ?? '');
            if ($policyNumber !== '' && str_contains($normalizedMessage, $this->normalizeForMatch($policyNumber))) {
                return $policy;
            }
        }

        if ($matchedPet !== null && preg_match('/\b(policy|policies|insurance)\b/i', $message)) {
            foreach ($policies as $policy) {
                if ($this->sameText($policy['pet_name'] ?? null, $matchedPet['name'] ?? null)) {
                    return $policy;
                }
            }
        }

        return null;
    }

    private function findMatchingClaim(string $message, array $claims, ?array $matchedPolicy = null, ?array $matchedPet = null): ?array
    {
        if (preg_match('/\bclaim\b\s*#?\s*(\d+)/i', $message, $matches)) {
            foreach ($claims as $claim) {
                if ((int) ($claim['id'] ?? 0) === (int) $matches[1]) {
                    return $claim;
                }
            }
        }

        if ($matchedPolicy !== null) {
            foreach ($claims as $claim) {
                if ($this->sameText($claim['policy_number'] ?? null, $matchedPolicy['policy_number'] ?? null)) {
                    return $claim;
                }
            }
        }

        if ($matchedPet !== null && preg_match('/\b(claim|claims)\b/i', $message)) {
            foreach ($claims as $claim) {
                if ($this->sameText($claim['pet_name'] ?? null, $matchedPet['name'] ?? null)) {
                    return $claim;
                }
            }
        }

        return null;
    }

    private function petLine(array $pet): string
    {
        return sprintf(
            '- %s | type: %s | breed: %s | insured: %s | vaccinated: %s',
            $this->value($pet['name'] ?? null, 'Pet #' . (string) ($pet['id'] ?? '')),
            $this->value($pet['type'] ?? null),
            $this->value($pet['breed'] ?? null),
            $this->value($pet['insured'] ?? null, 'unknown'),
            $this->value($pet['vaccinated'] ?? null, 'unknown')
        );
    }

    private function policyLine(array $policy): string
    {
        return sprintf(
            '- %s | pet: %s | provider: %s | coverage: %s | status: %s | period: %s to %s',
            $this->value($policy['policy_number'] ?? null, 'Policy #' . (string) ($policy['id'] ?? '')),
            $this->value($policy['pet_name'] ?? null),
            $this->value($policy['provider'] ?? null),
            $this->value($policy['coverage_type'] ?? null),
            $this->value($policy['status'] ?? null),
            $this->value($policy['start_date'] ?? null),
            $this->value($policy['end_date'] ?? null)
        );
    }

    private function claimLine(array $claim): string
    {
        return sprintf(
            '- Claim #%s | policy: %s | pet: %s | type: %s | status: %s | amount: %s | incident: %s',
            $this->value($claim['id'] ?? null),
            $this->value($claim['policy_number'] ?? null),
            $this->value($claim['pet_name'] ?? null),
            $this->value($claim['claim_type'] ?? null),
            $this->value($claim['status'] ?? null),
            $this->money($claim['amount'] ?? null),
            $this->value($claim['incident_date'] ?? null)
        );
    }

    private function petSentence(array $pet): string
    {
        return sprintf(
            '- Petsfolio found %s as a %s with breed %s. Age: %s. Gender: %s. Insured: %s. Vaccinated: %s.',
            $this->value($pet['name'] ?? null, 'Pet #' . (string) ($pet['id'] ?? '')),
            $this->humanizeValue($pet['type'] ?? null, 'pet'),
            $this->humanizeValue($pet['breed'] ?? null, 'not available'),
            $this->ageLabel($pet['dob'] ?? null) ?? 'not available',
            $this->humanizeValue($pet['gender'] ?? null, 'not available'),
            $this->humanizeFlag($pet['insured'] ?? null),
            $this->humanizeFlag($pet['vaccinated'] ?? null)
        );
    }

    private function policySentence(array $policy): string
    {
        return sprintf(
            '- Petsfolio found policy %s for %s. Provider: %s. Coverage: %s. Status: %s. Period: %s to %s.',
            $this->value($policy['policy_number'] ?? null, 'Policy #' . (string) ($policy['id'] ?? '')),
            $this->value($policy['pet_name'] ?? null, 'the pet'),
            $this->value($policy['provider'] ?? null, 'not available'),
            $this->value($policy['coverage_type'] ?? null, 'not available'),
            $this->value($policy['status'] ?? null, 'not available'),
            $this->value($policy['start_date'] ?? null, 'not available'),
            $this->value($policy['end_date'] ?? null, 'not available')
        );
    }

    private function claimSentence(array $claim): string
    {
        return sprintf(
            '- Petsfolio found claim #%s for %s under policy %s. Type: %s. Status: %s. Amount: %s. Incident date: %s.',
            $this->value($claim['id'] ?? null),
            $this->value($claim['pet_name'] ?? null, 'the pet'),
            $this->value($claim['policy_number'] ?? null, 'not available'),
            $this->value($claim['claim_type'] ?? null, 'not available'),
            $this->value($claim['status'] ?? null, 'not available'),
            $this->money($claim['amount'] ?? null),
            $this->value($claim['incident_date'] ?? null, 'not available')
        );
    }

    private function ageLabel(mixed $dob): ?string
    {
        $dobText = trim((string) ($dob ?? ''));
        if ($dobText === '') {
            return null;
        }

        try {
            $birthDate = new \DateTimeImmutable($dobText);
            $today = new \DateTimeImmutable('today');
        } catch (\Throwable $_) {
            return null;
        }

        if ($birthDate > $today) {
            return null;
        }

        $diff = $birthDate->diff($today);
        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y === 1 ? '' : 's');
        }

        if ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m === 1 ? '' : 's');
        }

        return $diff->d . ' day' . ($diff->d === 1 ? '' : 's');
    }

    private function humanizeFlag(mixed $value): string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));

        return match ($normalized) {
            'yes', 'y', '1', 'true' => 'Yes',
            'no', 'n', '0', 'false' => 'No',
            default => 'Not available',
        };
    }

    private function humanizeValue(mixed $value, string $fallback = 'N/A'): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return $fallback;
        }

        return ucwords(str_replace(['_', '-'], ' ', $text));
    }

    private function normalizeForMatch(string $text): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $text));
    }

    private function sameText(mixed $left, mixed $right): bool
    {
        $normalizedLeft = $this->normalizeForMatch((string) ($left ?? ''));
        $normalizedRight = $this->normalizeForMatch((string) ($right ?? ''));

        return $normalizedLeft !== '' && $normalizedLeft === $normalizedRight;
    }

    private function value(mixed $value, string $fallback = 'N/A'): string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? $fallback : $text;
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        return '$' . number_format((float) $value, 2);
    }

    private function missingUserIdMessage(string $locale): string
    {
        return 'Petsfolio needs a `user_id`. Please include something like `user_id: 123` so Petsfolio can check pets, policies, and claims.';
    }

    private function databaseErrorMessage(string $locale): string
    {
        return 'Petsfolio could not read pets, policies, and claims from the database right now. Please try again in a moment.';
    }

    private function headerMessage(int $userId, string $locale): string
    {
        return sprintf('Petsfolio found the following details for user_id %d.', $userId);
    }

    private function notFoundMessage(int $userId, string $locale): string
    {
        return sprintf('Petsfolio could not find pets, policies, or claims for user_id %d.', $userId);
    }
}
