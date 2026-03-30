<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;


class ChatbotController extends BaseController
{
  // ---------------- QUERY
  
  public function query()
  {
    $message = strtolower(trim($this->request->getPost('message')));
    $userId  = $this->request->getPost('user_id');

    $intent = $this->detectIntent($message);

    if ($intent === 'invalid') {
      return $this->respond(['status' => false, 'message' => 'Invalid query']);
    }

    switch ($intent) {
      case 'booking':
        return $this->getBooking($userId);

      case 'pet':
        return $this->getPet($userId);

      case 'insurance':
        return $this->analyzeInsurance($userId);

      case 'document':
        return $this->analyzeDocument();
    }
  }

  private function detectIntent($msg)
  {
    if (preg_match('/insurance|policy/', $msg)) return 'insurance';
    if (preg_match('/booking|walk/', $msg)) return 'booking';
    if (preg_match('/pet|dog|cat/', $msg)) return 'pet';
    if (preg_match('/document|pdf|report/', $msg)) return 'document';

    return 'invalid';
  }

  // ---------------- BOOKING
  private function getBooking($userId)
  {
    $booking = model('BookingModel')
      ->where('user_id', $userId)
      ->orderBy('date', 'DESC')
      ->first();

    return $this->respond(['type' => 'booking', 'data' => $booking]);
  }

  // ---------------- PET
  private function getPet($userId)
  {
    $pet = model('PetModel')->where('user_id', $userId)->first();

    return $this->respond(['type' => 'pet', 'data' => $pet]);
  }

  // ---------------- INSURANCE
  private function analyzeInsurance($userId)
  {
    $insurance = null;
    try {
      $insModel = model('InsuranceModel');
      if ($insModel) {
        $insurance = $insModel->where('user_id', $userId)->first();
      }
    } catch (\Throwable $_) {
      $insurance = null;
    }

    $llm = service('llm');
    $locale = session()->get('locale') ?? 'en';

    $prompt = "
Analyze insurance:
" . json_encode($insurance) . "

Return JSON:
{
  \"summary\": \"\",
  \"issues\": [],
  \"recommendation\": \"\"
}";

    $raw = $llm->ask($prompt, $locale);
    $result = json_decode($raw, true);

    if (!is_array($result)) {
        // If the LLM returned a refusal or non-JSON, normalize to a refusal or empty structure
        $englishTrigger = 'I can only assist';
        if (stripos($raw, $englishTrigger) !== false || stripos($raw, 'Translation:') !== false) {
            $result = ['error' => 'refusal', 'message' => $llm->refusal($locale)];
        } else {
            $result = ['summary' => '', 'issues' => [], 'recommendation' => ''];
        }
    }

    return $this->respond(['type' => 'insurance', 'data' => $result]);
  }

  // ---------------- DOCUMENT FLOW
  private function analyzeDocument()
  {
    $userFile   = $this->request->getFile('user_doc');
    $systemFile = $this->request->getFile('system_doc');

    if (!$userFile || !$systemFile) {
      return $this->respond(['status' => false, 'message' => 'Both files required']);
    }

    $parser  = service('docparser');
    $llm     = service('llm');
    $compare = service('compare');

    // STEP 1: Parse
    $userText   = $parser->parse($userFile);
    $systemText = $parser->parse($systemFile);

    $locale = session()->get('locale') ?? 'en';
    // STEP 2: Extract JSON
    $rawUser = $llm->ask($this->extractPrompt($userText), $locale);
    $userJson = json_decode($rawUser, true);
    if (!is_array($userJson)) {
      $englishTrigger = 'I can only assist';
      if (stripos($rawUser, $englishTrigger) !== false || stripos($rawUser, 'Translation:') !== false) {
        $userJson = [];
      } else {
        $userJson = [];
      }
    }

    $rawSystem = $llm->ask($this->extractPrompt($systemText), $locale);
    $systemJson = json_decode($rawSystem, true);
    if (!is_array($systemJson)) {
      $englishTrigger = 'I can only assist';
      if (stripos($rawSystem, $englishTrigger) !== false || stripos($rawSystem, 'Translation:') !== false) {
        $systemJson = [];
      } else {
        $systemJson = [];
      }
    }

    // STEP 3: Compare
    $comparison = $compare->compare($systemJson, $userJson);

    return $this->respond([
      'type'        => 'document',
      'comparison'  => $comparison,
      'user_data'   => $userJson,
      'system_data' => $systemJson
    ]);
  }

  private function extractPrompt($text)
  {
    return "
Extract:
- pet_name
- vaccines (name, date, expiry)

Return JSON.

$text
";
  }
}
