<?php

use App\Services\RecommendationService;
use CodeIgniter\Test\CIUnitTestCase;

final class RecommendationServiceTest extends CIUnitTestCase
{
    public function testInfersDogPetType(): void
    {
        $service = new RecommendationService();

        $this->assertSame('dog', $service->inferPetType('I need the best plan for my dog'));
        $this->assertSame('cat', $service->inferPetType('मेरी कैट के लिए कौन सा प्लान सही है?'));
    }

    public function testSelectsPlanByPreference(): void
    {
        $service = new RecommendationService();
        $plans = [
            ['price_monthly' => 20],
            ['price_monthly' => 40],
            ['price_monthly' => 60],
        ];

        $this->assertSame(20, $service->pickPlan($plans, 'budget')['price_monthly']);
        $this->assertSame(40, $service->pickPlan($plans, 'balanced')['price_monthly']);
        $this->assertSame(60, $service->pickPlan($plans, 'premium')['price_monthly']);
    }
}
