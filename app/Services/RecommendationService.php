<?php

namespace App\Services;

class RecommendationService
{
    public function inferPetType(string $text): ?string
    {
        $text = mb_strtolower($text);

        if (preg_match('/\b(dog|dogs|puppy|canine)\b|à¤¡à¥‰à¤—|à¤•à¥à¤¤à¥à¤¤à¤¾|కుక్క|ನಾಯಿ|நாய்/u', $text)) {
            return 'dog';
        }

        if (preg_match('/\b(cat|cats|kitten|feline)\b|à¤•à¥ˆà¤Ÿ|à¤¬à¤¿à¤²à¥à¤²à¥€|పిల్లి|ಬೆಕ್ಕು|பூனை/u', $text)) {
            return 'cat';
        }

        return null;
    }

    public function preference(string $text): string
    {
        $text = mb_strtolower($text);

        if (preg_match('/\b(budget|cheap|affordable|lowest|low cost|starter)\b|à¤¸à¤¸à¥à¤¤à¤¾|à¤•à¤¿à¤«à¤¾à¤¯à¤¤à¥€|à¤¬à¤œà¤Ÿ|చవక|బడ్జెట్|ಅಗ್ಗದ|ಬಜೆಟ್|மலிவு|பட்ஜெட்/u', $text)) {
            return 'budget';
        }

        if (preg_match('/\b(best|premium|highest|maximum|comprehensive|senior|specialist|emergency)\b|à¤ªà¥à¤°à¥€à¤®à¤¿à¤¯à¤®|à¤¬à¥‡à¤¹à¤¤à¤°|à¤¸à¤¬à¤¸à¥‡ à¤…à¤§à¤¿à¤•|à¤¸à¥€à¤¨à¤¿à¤¯à¤°|ప్రీమియం|ఉత్తమ|ಅತ್ಯುತ್ತಮ|ಪ್ರೀಮಿಯಂ|பிரீமியம்|சிறந்த/u', $text)) {
            return 'premium';
        }

        return 'balanced';
    }

    public function pickPlan(array $plans, string $preference): ?array
    {
        if ($plans === []) {
            return null;
        }

        usort($plans, static fn(array $left, array $right): int => (float) $left['price_monthly'] <=> (float) $right['price_monthly']);

        return match ($preference) {
            'budget' => $plans[0],
            'premium' => $plans[array_key_last($plans)],
            default => $plans[(int) floor(count($plans) / 2)],
        };
    }
}
