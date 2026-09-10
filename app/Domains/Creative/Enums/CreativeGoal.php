<?php

namespace App\Domains\Creative\Enums;

enum CreativeGoal: string
{
    case Introduction = 'introduction';
    case Sales = 'sales';
    case Branding = 'branding';
    case Promotion = 'promotion';
    case Launch = 'launch';
    case Engagement = 'engagement';
}
