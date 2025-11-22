<?php

namespace App;

class Utils
{
    /**
     * Generate an 8-character unique identifier
     * Uses alphanumeric characters (uppercase and lowercase)
     *
     * @return string 8-character unique ID
     */
    public static function generateUniqueId()
    {
        // Method 1: Using random_bytes (cryptographically secure)
        $bytes = random_bytes(6);
        $id = substr(bin2hex($bytes), 0, 8);
        return strtoupper($id);
    }

    /**
     * Generate an 8-character unique identifier with custom character set
     * Uses uppercase letters and numbers only (more readable)
     *
     * @return string 8-character unique ID
     */
    public static function generateReadableId()
    {
        $characters = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // Excludes I and O for readability
        $id = '';

        for ($i = 0; $i < 8; $i++) {
            $id .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $id;
    }

    /**
     * Generate an 8-character unique identifier with mixed case
     * Uses uppercase, lowercase letters and numbers
     *
     * @return string 8-character unique ID
     */
    public static function generateMixedCaseId()
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $id = '';

        for ($i = 0; $i < 8; $i++) {
            $id .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $id;
    }

    /**
     * Generate an 8-character numeric-only unique identifier
     *
     * @return string 8-character numeric ID
     */
    public static function generateNumericId()
    {
        return str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Generate an 8-character timestamp-based unique identifier
     * First 4 chars are base36 timestamp, last 4 are random
     *
     * @return string 8-character unique ID
     */
    public static function generateTimestampId()
    {
        $timestamp = base_convert((string)time(), 10, 36);
        $timestamp = strtoupper(substr($timestamp, -4));

        $random = substr(bin2hex(random_bytes(2)), 0, 4);
        $random = strtoupper($random);

        return $timestamp . $random;
    }
}
