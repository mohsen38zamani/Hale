<?php

namespace Tests\Unit\AI;

use App\Domains\AI\Services\EnglishTranslator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnglishTranslatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The cache outlives a test run (redis), so a previous run would hand
        // back an answer this one has not asked for yet.
        Cache::flush();
    }

    private function modelReplies(string $text, int $status = 200): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => $text]]]]],
            ], $status),
        ]);
    }

    public function test_it_leaves_a_line_alone_when_there_is_nothing_to_translate(): void
    {
        Http::fake();
        $translator = new EnglishTranslator('test-key');

        $this->assertNull($translator->toEnglish(null));
        $this->assertSame('', $translator->toEnglish(''));
        $this->assertSame(
            'A leather and vanilla fragrance',
            $translator->toEnglish('A leather and vanilla fragrance'),
        );

        Http::assertNothingSent();
    }

    public function test_it_keeps_the_persian_line_when_the_model_is_not_configured(): void
    {
        Http::fake();
        $translator = new EnglishTranslator('');

        $this->assertSame('رایحه چرم و وانیل', $translator->toEnglish('رایحه چرم و وانیل'));

        Http::assertNothingSent();
    }

    public function test_it_translates_persian_prose_so_an_image_model_can_read_it(): void
    {
        $this->modelReplies('A royal fragrance with a warm, friendly tone');
        $translator = new EnglishTranslator('test-key');

        $this->assertSame(
            'A royal fragrance with a warm, friendly tone',
            $translator->toEnglish('رایحه‌ای سلطنتی با لحنی گرم و صمیمی'),
        );
    }

    public function test_it_asks_once_for_the_same_words_whatever_runs_first(): void
    {
        $this->modelReplies('A leather and vanilla fragrance');
        $translator = new EnglishTranslator('test-key');
        $source = 'رایحه چرم و وانیل';

        $first = $translator->toEnglish($source);
        $second = $translator->toEnglish($source);

        $this->assertSame($first, $second, 'The prompt must not depend on which run warmed the cache.');
        Http::assertSentCount(1);
    }

    public function test_it_hands_back_the_original_line_when_the_model_fails(): void
    {
        $this->modelReplies('', 500);
        $translator = new EnglishTranslator('test-key');

        $this->assertSame('رایحه چرم و وانیل', $translator->toEnglish('رایحه چرم و وانیل'));

        // The miss is cached only briefly, so the studio's words survive the
        // outage without every preview repeating the dead request.
        Http::assertSentCount(1);
        $this->assertSame('رایحه چرم و وانیل', $translator->toEnglish('رایحه چرم و وانیل'));
        Http::assertSentCount(1);
    }

    public function test_it_discards_an_answer_the_model_wrote_back_in_persian(): void
    {
        $this->modelReplies('رایحه چرم و وانیل');
        $translator = new EnglishTranslator('test-key');

        $this->assertSame(
            'رایحه چرم و وانیل',
            $translator->toEnglish('رایحه چرم و وانیل'),
            'A translation the model did not perform is the original line, not an improvement.',
        );
    }

    public function test_it_strips_a_fence_the_model_wraps_the_answer_in(): void
    {
        $this->modelReplies("```\nA leather and vanilla fragrance\n```");
        $translator = new EnglishTranslator('test-key');

        $this->assertSame('A leather and vanilla fragrance', $translator->toEnglish('رایحه چرم و وانیل'));
    }

    public function test_it_strips_quotation_marks_the_model_wraps_the_answer_in(): void
    {
        $this->modelReplies('"A leather and vanilla fragrance"');
        $translator = new EnglishTranslator('test-key');

        $this->assertSame(
            'A leather and vanilla fragrance',
            $translator->toEnglish('رایحه چرم و وانیل با تخفیف'),
            'Quotation marks belong to the answer, never to the prompt.',
        );
    }
}
