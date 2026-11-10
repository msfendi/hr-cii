<?php

namespace App\Services\ApprovalWa;

class PhoneFormatter
{
    /**
     * Normalize phone number to international format (62xxx).
     * Handles: 08xxx, +62xxx, 62xxx, 8xxx, with spaces/dashes.
     */
    public static function normalize(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Strip spaces, dashes, dots, parentheses
        $phone = preg_replace('/[\s\-\.\(\)]+/', '', $phone);

        // Remove leading +
        $phone = ltrim($phone, '+');

        // Already 62xxx
        if (str_starts_with($phone, '62')) {
            return $phone;
        }

        // 08xxx → 628xxx
        if (str_starts_with($phone, '08')) {
            return '62' . substr($phone, 1);
        }

        // 8xxx (no leading zero)
        if (str_starts_with($phone, '8')) {
            return '62' . $phone;
        }

        // Unknown format — return null so we skip sending
        return null;
    }

    /**
     * Validate that the normalized number looks like an Indonesian mobile number.
     * 628xx with 10–15 digits total.
     */
    public static function isValid(?string $normalized): bool
    {
        if (empty($normalized)) {
            return false;
        }

        return (bool) preg_match('/^628[0-9]{8,12}$/', $normalized);
    }
}
