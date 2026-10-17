<?php

declare(strict_types=1);

namespace App\Domains\Generations\Contracts;

use App\Domains\Media\Models\MediaAsset;

/**
 * تحلیل تصویر محصول برای «تحلیل دورگه» (بند ۱۲ — آیتم ۱).
 *
 * لایهٔ ۱ (محلی، همیشه و بدون شبکه): ابعاد، نسبت کادر، آلفا و جعبهٔ امن.
 * لایهٔ ۲ (دورگه، مدل بینایی در صف): دستهٔ صنف پیشنهادی + سبک + رنگ غالب.
 */
interface ProductImageAnalyzerInterface
{
    /**
     * آنالیز محلی همیشه در دسترس است (بدون شبکه).
     *
     * @return array{width:int,height:int,ratio:float,has_alpha:bool,safe_zone:array{left:int,top:int,right:int,bottom:int},analyzable:bool}
     */
    public function analyzeLocally(MediaAsset $asset): array;

    /**
     * پیشنهاد دورگهٔ صنف (لایهٔ ۲) از کش؛ اگر نبود، job تحلیل را صف می‌کند.
     *
     * اگر تحلیل محلی را زودتر محاسبه کرده‌اید همان را پاس بدهید تا فایل
     * دوباره از دیسک خوانده نشود. `unavailable` یعنی تلاش اخیر برای لایهٔ
     * بینایی شکست خورده و تا سررسید دوباره صف نمی‌شود.
     *
     * @param  array{width:int,height:int,ratio:float,has_alpha:bool,safe_zone:array{left:int,top:int,right:int,bottom:int},analyzable:bool}|null  $local
     * @return array{status:'ready'|'pending'|'none'|'unavailable',suggestion:?array{category:string,confidence:float,style_hint:?string,key_color:?string}}
     */
    public function suggestion(MediaAsset $asset, ?array $local = null): array;

    /**
     * فراخوانی مدل بینایی (لایهٔ ۲) و ذخیرهٔ نتیجه در cache.
     */
    public function analyzeWithVision(MediaAsset $asset): void;
}
