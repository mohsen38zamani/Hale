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
        return $this === self::InstagramPost ? 'image' : ($this === self::InstagramStory ? 'image' : 'video');
    }

    public function aspectRatio(): string
    {
        return $this === self::InstagramPost ? '1:1' : '9:16';
    }
}
