<?php

class Sj4webRelancepanierCrypto
{
    // Chiffre l'email (AES-256-GCM). Retourne un token base64url(nonce|cipher|tag).
    public static function encryptEmail(string $email, string $key): string
    {
        $email = Tools::strtolower(trim($email));
        $nonce = random_bytes(12); // GCM nonce 96 bits
        $tag   = '';
        $cipher= openssl_encrypt($email, 'aes-256-gcm', self::k($key), OPENSSL_RAW_DATA, $nonce, $tag);
        return self::b64url($nonce.$cipher.$tag);
    }

    // Tente de déchiffrer avec key courante puis précédente.
    public static function decryptToken(string $token, string $key, ?string $prevKey = null): ?string
    {
        $raw = self::b64url_decode($token);
        if ($raw === false || strlen($raw) < 12+16) return null;
        $nonce = substr($raw, 0, 12);
        $tag   = substr($raw, -16);
        $cipher= substr($raw, 12, -16);

        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::k($key), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($plain !== false) return Tools::strtolower(trim($plain));

        if ($prevKey) {
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::k($prevKey), OPENSSL_RAW_DATA, $nonce, $tag);
            if ($plain !== false) return Tools::strtolower(trim($plain));
        }
        return null;
    }

    // Hash statique (utile si tu veux t’en servir, on le remplit quand même proprement)
    public static function emailStaticHash(string $email): string
    {
        return hash('sha256', Tools::strtolower(trim($email)));
    }

    private static function k(string $k): string { return hash('sha256', $k, true); }
    private static function b64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
    private static function b64url_decode(string $s) { $p=strlen($s)%4; if($p){$s.=str_repeat('=',4-$p);} return base64_decode(strtr($s,'-_','+/')); }
}