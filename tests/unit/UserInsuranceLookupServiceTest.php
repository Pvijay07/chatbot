<?php

use App\Services\UserInsuranceLookupService;
use CodeIgniter\Test\CIUnitTestCase;

final class UserInsuranceLookupServiceTest extends CIUnitTestCase
{
    public function testDetectsLookupIntentAndUserId(): void
    {
        $service = new UserInsuranceLookupService();

        $this->assertTrue($service->shouldHandle('check my pets and pet insurance policies,claims'));
        $this->assertSame(77, $service->resolveTargetUserId('check pets for user_id: 77', null, 5));
        $this->assertSame(['pets', 'policies', 'claims'], $service->requestedSections('check my pets and pet insurance policies,claims'));
    }

    public function testBuildsFormattedReplyFromRecords(): void
    {
        $service = new UserInsuranceLookupService();
        $result = $service->buildResponse(
            7,
            ['pets', 'policies', 'claims'],
            [[
                'id'         => 11,
                'name'       => 'Buddy',
                'type'       => 'dog',
                'breed'      => 'Beagle',
                'insured'    => 'yes',
                'vaccinated' => 'yes',
            ]],
            [[
                'id'            => 21,
                'policy_number' => 'POL-7001',
                'pet_name'      => 'Buddy',
                'provider'      => 'Petsfolio',
                'coverage_type' => 'Premium',
                'status'        => 'Active',
                'start_date'    => '2026-01-01',
                'end_date'      => '2026-12-31',
            ]],
            [[
                'id'            => 31,
                'policy_number' => 'POL-7001',
                'pet_name'      => 'Buddy',
                'claim_type'    => 'Accident',
                'status'        => 'Pending',
                'amount'        => 1250.50,
                'incident_date' => '2026-03-15',
            ]],
            'en'
        );

        $this->assertIsArray($result);
        $this->assertStringContainsString('Petsfolio found the following details for user_id 7.', $result['reply']);
        $this->assertStringContainsString('Buddy', $result['reply']);
        $this->assertStringContainsString('POL-7001', $result['reply']);
        $this->assertStringContainsString('claim #31', $result['reply']);
        $this->assertSame([], $result['sources']);
    }

    public function testUsesHistoryUserIdForFollowUpQuestions(): void
    {
        $service = new UserInsuranceLookupService();
        $history = [
            ['message' => 'Petsfolio found the following details for user_id 271.'],
        ];

        $this->assertTrue($service->shouldHandle('tell me about Pet2 Policy', null, $history));
        $this->assertSame(271, $service->extractContextUserIdFromHistory($history));
        $this->assertSame(271, $service->resolveTargetUserId('tell me about Pet2 Policy', null, 2468711682, $history));
    }

    public function testAnswersSpecificPetPolicyFollowUp(): void
    {
        $service = new UserInsuranceLookupService();
        $reply = $service->answerFromRecords(
            'tell me about Pet2 Policy',
            271,
            [
                ['id' => 1, 'name' => 'Pet1', 'type' => 'dog', 'breed' => 'afghan hound', 'insured' => 'no', 'vaccinated' => 'no'],
                ['id' => 2, 'name' => 'Pet2', 'type' => 'dog', 'breed' => 'afghan hound', 'insured' => 'no', 'vaccinated' => 'no'],
            ],
            [
                ['id' => 11, 'policy_number' => 'PF696978', 'pet_name' => 'Pet2', 'provider' => null, 'coverage_type' => 'Basic', 'status' => 'Active', 'start_date' => '2026-04-02', 'end_date' => '2027-04-02'],
            ],
            [],
            'en'
        );

        $this->assertStringContainsString('Petsfolio found the following policy details for Pet2', $reply);
        $this->assertStringContainsString('PF696978', $reply);
        $this->assertStringContainsString('Status: Active', $reply);
    }

    public function testAnswersSpecificPetBreedAndAgeFollowUp(): void
    {
        $service = new UserInsuranceLookupService();
        $today = date('Y-m-d');

        $breedReply = $service->answerFromRecords(
            'what is the breed of Pet2',
            271,
            [
                ['id' => 2, 'name' => 'Pet2', 'type' => 'dog', 'breed' => 'afghan hound', 'dob' => $today, 'insured' => 'no', 'vaccinated' => 'no'],
            ],
            [],
            [],
            'en'
        );

        $ageReply = $service->answerFromRecords(
            'what is the age of Pet2',
            271,
            [
                ['id' => 2, 'name' => 'Pet2', 'type' => 'dog', 'breed' => 'afghan hound', 'dob' => $today, 'insured' => 'no', 'vaccinated' => 'no'],
            ],
            [],
            [],
            'en'
        );

        $this->assertStringContainsString('Petsfolio found that Pet2 has breed afghan hound.', $breedReply);
        $this->assertStringContainsString('Petsfolio found that Pet2 is 0 days old.', $ageReply);
    }

    public function testAsksForUserIdWhenLookupNeedsOne(): void
    {
        $service = new UserInsuranceLookupService();
        $result = $service->replyForMessage('show pets and claims', null, 0, [], 'en');

        $this->assertIsArray($result);
        $this->assertStringContainsString('Petsfolio needs a `user_id`', $result['reply']);
        $this->assertSame([], $result['sources']);
    }
}
