<?php

namespace Tests\Unit\AI;

use App\Domains\AI\Services\PromptModerator;
use Tests\TestCase;

class PromptModeratorTest extends TestCase
{
    public function test_it_passes_clean_prompts(): void
    {
        $moderator = new PromptModerator;

        $this->assertTrue($moderator->passes('عطر لوکس در محیط مدرن و زیبا'));
        $this->assertTrue($moderator->passes('Modern minimalist shoe photography'));
    }

    public function test_it_blocks_inappropriate_english_and_persian_prompts(): void
    {
        $moderator = new PromptModerator;

        $this->assertFalse($moderator->passes('Explicit pornographic content'));
        $this->assertFalse($moderator->passes('تصویر شامل محتوای مستهجن و نامناسب'));
        $this->assertFalse($moderator->passes('پورنوگرافی در پس‌زمینه'));
    }
}
