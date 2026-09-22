<?php

namespace App\Support;

class PhoneNormalizer
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }

        // Convert Persian and Arabic digits to English digits
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $phone = str_replace($persian, $english, $phone);
        $phone = str_replace($arabic, $english, $phone);

        // Remove non-digit and non-plus characters
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Normalize Iranian phone format to international E.164 (+98...)
        if (str_starts_with($phone, '0098')) {
            $phone = '+98'.substr($phone, 4);
        } elseif (str_starts_with($phone, '09') && strlen($phone) === 11) {
            $phone = '+98'.substr($phone, 1);
        } elseif (str_starts_with($phone, '9') && strlen($phone) === 10) {
            $phone = '+98'.$phone;
        } elseif (! str_starts_with($phone, '+') && strlen($phone) >= 10) {
            $phone = '+'.$phone;
        }

        return $phone;
    }
}
