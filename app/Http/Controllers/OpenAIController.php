<?php

namespace App\Http\Controllers;

use App\Services\OpenAIService;
use App\Http\Requests\TextToSpeechRequest;

class OpenAIController extends Controller
{
    protected $openAIService;

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    public function textToSpeech(TextToSpeechRequest $request)
    {

        $input = $request->input('input');
        $model = $request->input('model');
        $voice = $request->input('voice');
        $responseFormat = $request->input('response_format');
        $speed = $request->input('speed');

        try {
            $response = $this->openAIService->textToSpeech($model, $input, $voice, $responseFormat, $speed);
            return response()->json($response);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }
}

