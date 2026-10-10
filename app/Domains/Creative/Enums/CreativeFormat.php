<?php

namespace App\Domains\Creative\Enums;

enum CreativeFormat: string
{
    case InstagramPost = 'instagram_post';
    case InstagramStory = 'instagram_story';
    case InstagramReel = 'instagram_reel';
    case TikTok = 'tiktok';

    public function type(): string
    {
        return match ($this) {
            self::InstagramPost, self::InstagramStory => 'image',
            default => 'video',
        };
    }

    public function aspectRatio(): string
    {
        return $this === self::InstagramPost ? '1:1' : '9:16';
    }

    /**
     * The frame as an image model should read it: a shape word and a ratio,
     * never the studio's format key. `Composition: 1:1 ratio (instagram_post)`
     * quoted a field name the model has no use for; `Square 1:1 frame` is the
     * framing instruction itself.
     */
    public function frame(): string
    {
        $shape = match ($this->aspectRatio()) {
            '1:1' => 'Square',
            '9:16' => 'Vertical',
            default => 'Framed',
        };

        return sprintf('%s %s frame.', $shape, $this->aspectRatio());
    }
}
