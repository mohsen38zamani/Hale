<?php

namespace App\Domains\AI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * One English sentence for the Persian prose the studio writes about a
 * product.
 *
 * The image models Hale talks to are captioned overwhelmingly in English,
 * so a description or a scene line written in Persian reaches them as
 * noise: the words that name the colour, the material and the finish never
 * turn into pixels. This turns them into a sentence the model can read,
 * over the same Google credentials the caption generator uses.
 *
 * The answer is cached against a hash of the source, not of the product, so
 * the same words never cost twice while a changed line never reuses the old
 * answer. It is asked for at temperature zero: the studio builds its prompt
 * on this text, and a caption that drifts between two runs would make the
 * stored prompt and the preview disagree.
 *
 * Like every text call in the app it never hard-fails. Unconfigured,
 * unreachable, blocked or unparseable all hand back the original line, so
 * the prompt still holds what the studio wrote, in the language it wrote it.
 */
class EnglishTranslator
{
    private string $apiKey;

    private string $baseUrl;

    private string $model;

    private int $timeout;

    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $model = null,
        ?int $timeout = null,
    ) {
        $this->apiKey = $apiKey ?? (string) config('ai.providers.google.api_key', '');
        $this->baseUrl = $baseUrl ?? (string) config('ai.providers.google.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->model = $model ?? (string) config('ai.providers.google.text_model', 'gemini-2.0-flash');
        // Deliberately shorter than the provider timeout: this runs inside a
        // preview, where a slow text model must cost the user a few hundred
        // milliseconds rather than a spinner that outlives the request.
        $this->timeout = $timeout ?? (int) config('ai.providers.google.translate_timeout', 8);
    }

    /**
     * The line to put in the prompt: English when it was Persian and the
     * model was willing, untouched when it was already readable or nothing
     * could be done about it.
     */
    public function toEnglish(?string $text): ?string
    {
        if ($text === null || trim($text) === '' || ! $this->isPersian($text)) {
            // Empty, or already in a script the models were trained on.
            return $text;
        }

        $source = trim($text);
        $key = 'ai.translate.en:'.sha1($source);

        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if ($this->apiKey === '') {
            // Nothing configured: hand back the Persian rather than pretending
            // an empty key is a reason to drop the studio's words.
            return $text;
        }

        $translated = $this->ask($source);

        // A failure is cached for a minute - long enough that a dead text
        // model does not cost a request per keystroke, short enough that the
        // next attempt is not a month away - while a good answer stays.
        Cache::put(
            $key,
            $translated ?? $source,
            $translated === null ? now()->addMinute() : now()->addDays(30),
        );

        return $translated ?? $text;
    }

    private function isPersian(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $text);
    }

    private function ask(string $source): ?string
    {
        $url = rtrim($this->baseUrl, '/')."/models/{$this->model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'contents' => [[
                        'parts' => [[
                            'text' => "Translate this Persian text about a product into natural English for an advertising image model. Keep every colour, material and finish exactly as stated. Return only the translation, with no quotation marks and no notes.\n\n".$source,
                        ]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.0,
                        'maxOutputTokens' => 300,
                    ],
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $answer = $this->unwrap((string) ($response->json('candidates.0.content.parts.0.text') ?? ''));

        // A model that answered in Persian, or not at all, has not moved the
        // prompt forward. Anything with Latin letters in it is a real answer,
        // even one that keeps a brand name in the language it came in.
        if ($answer === '' || ! preg_match('/[A-Za-z]/', $answer)) {
            return null;
        }

        return $answer;
    }

    /**
     * Models like to hand back a translation wrapped in a fence or in
     * quotation marks; neither belongs in a prompt.
     */
    private function unwrap(string $answer): string
    {
        $answer = trim($answer);
        $answer = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/m', '', $answer) ?? $answer;
        $answer = trim($answer);

        if (strlen($answer) >= 2) {
            $first = $answer[0];
            $last = $answer[strlen($answer) - 1];
            if (($first === '"' && $last === '"') || ($first === '«' && $last === '»') || ($first === '“' && $last === '”')) {
                $answer = trim(substr($answer, 1, -1));
            }
        }

        return $answer;
    }
}
