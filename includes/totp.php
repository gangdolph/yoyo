<?php
/**
 * Minimal TOTP utilities for 2FA.
 */

function generate_base32_secret($length = 16) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, 31)];
    }
    return $secret;
}

function base32_decode($b32) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper($b32);
    $b32 = preg_replace('/[^A-Z2-7]/', '', $b32);
    $binaryString = '';
    $len = strlen($b32);
    for ($i = 0; $i < $len; $i++) {
        $val = strpos($alphabet, $b32[$i]);
        if ($val === false) {
            continue;
        }
        $binaryString .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($binaryString, 8) as $byte) {
        if (strlen($byte) === 8) {
            $bytes .= chr(bindec($byte));
        }
    }
    return $bytes;
}

function verify_totp($secret, $code, $window = 1) {
    $secretKey = base32_decode($secret);
    $timeSlice = floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        $slice = $timeSlice + $i;
        $time = pack('N*', 0) . pack('N*', $slice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord($hash[19]) & 0xf;
        $binary = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        );
        $otp = str_pad((string)($binary % 1000000), 6, '0', STR_PAD_LEFT);
        if (hash_equals($otp, $code)) {
            return true;
        }
    }
    return false;
}
?>
