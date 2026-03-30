<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

class VoiceController extends BaseController
{
  public function process()
  {
    $audio = $this->request->getFile ( 'audio' );

    if ( !$audio ) {
      return $this->fail ( 'Audio required' );
    }

    // STEP 1: Send to STT
    $client = \Config\Services::curlrequest ();

    $stt = $client->post ( 'http://localhost:5001/transcribe', [
      'multipart' => [
        [
          'name'     => 'audio',
          'contents' => fopen ( $audio->getTempName (), 'r' )
        ]
      ]
    ] );

    $text = json_decode ( $stt->getBody (), true )['text'];

    // STEP 2: Process chatbot
    $chatbot  = new \App\Controllers\Api\ChatbotController();
    $response = $chatbot->queryFromVoice ( $text );

    $replyText = $response['message'] ?? json_encode ( $response );

    // STEP 3: Convert to speech
    $tts = $client->post ( 'http://localhost:5002/speak', [
      'json' => ['text' => $replyText]
    ] );

    return $this->response
      ->setContentType ( 'audio/wav' )
      ->setBody ( $tts->getBody () );
  }
}