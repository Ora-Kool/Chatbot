<?php

namespace App\Http\Controllers;

use Gemini\Client;
use Gemini\Data\Part;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Gemini\Enums\Role;
use Gemini\Data\Content;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    private $client, $model;
    public function __construct()
    {
        $this->client = \Gemini::client(env("GEMINI_API_KEY"));
        $this->model = $this->client->generativeModel('gemini-2.0-flash');
    }

    private function searchWeb($query){
        $response = Http::get('https://www.googleapis.com/customsearch/v1', [
            'key' => env('GOOGLE_SEARCH_API_KEY'),
            'cx' => env('GOOGLE_SEARCH_ENGINE_ID'),
            'q' => $query,
            'num' => 3
        ]);

        return $response->json()['items'] ?? [];
    }

    public function chat(Request $request)
    {

        $message = $request->input('message');
        $history = $request->input('history', []);

        // Web search logic remains the same
        /*$needsSearch = $this->needsWebSearch($message);
        if ($needsSearch) {
            $results = $this->searchWeb($message);
            $context = "Current web search results:\n";
            foreach ($results as $i => $result) {
                $context .= ($i+1).". {$result['title']}\n{$result['snippet']}\n\n";
            }
            $history[] = ['role' => 'system', 'parts' => [['text' => $context]]];
        }*/


        $existing = new Content(parts: [new Part(text: $message)]);

        return response()->stream(function () use ($history, $existing) {

            $stream = $this->model->streamGenerateContent($existing);

            if (ob_get_level() === 0){
                ob_start();
            }
            foreach ($stream as $chunk) {
                $text = $chunk->text();
                echo "data: " . json_encode(['text' => $text]) . "\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
                usleep(10000);
            }

            if (ob_get_level() > 0) ob_end_flush();
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no'
        ]);
    }


    private function needsWebSearch($message) {
        $triggers = ['current', 'latest', 'news', 'update', 'search', 'find'];
        foreach ($triggers as $trigger){
            if (stripos($message, $trigger) !== false){
                return true;
            }
        }
        return false;
    }
}
