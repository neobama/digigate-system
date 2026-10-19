<?php

namespace App\Services;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class RepairPortalCaptcha
{
    public const SESSION_KEY = 'repair_portal_captcha';

    public static function regenerate(): string
    {
        $code = strtoupper(Str::substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 5));

        Session::put(self::SESSION_KEY, [
            'answer' => $code,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        return $code;
    }

    public static function code(): string
    {
        $payload = Session::get(self::SESSION_KEY);

        if (! is_array($payload) || empty($payload['answer']) || ($payload['expires_at'] ?? 0) < now()->timestamp) {
            return static::regenerate();
        }

        return (string) $payload['answer'];
    }

    public static function svg(): string
    {
        $code = static::code();
        $width = 180;
        $height = 56;
        $chars = str_split($code);
        $noise = '';

        for ($i = 0; $i < 6; $i++) {
            $x1 = random_int(0, $width);
            $y1 = random_int(0, $height);
            $x2 = random_int(0, $width);
            $y2 = random_int(0, $height);
            $noise .= sprintf(
                '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#b6dff3" stroke-width="1"/>',
                $x1,
                $y1,
                $x2,
                $y2
            );
        }

        $letters = '';
        foreach ($chars as $index => $char) {
            $x = 22 + ($index * 30) + random_int(-2, 2);
            $y = 36 + random_int(-4, 4);
            $rotate = random_int(-18, 18);
            $letters .= sprintf(
                '<text x="%d" y="%d" fill="#0a6eb4" font-size="26" font-family="Arial, Helvetica, sans-serif" font-weight="700" transform="rotate(%d %d %d)">%s</text>',
                $x,
                $y,
                $rotate,
                $x,
                $y,
                htmlspecialchars($char, ENT_QUOTES, 'UTF-8')
            );
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" role="img" aria-label="Captcha">
  <rect width="100%" height="100%" rx="10" fill="#eef7fc"/>
  {$noise}
  {$letters}
</svg>
SVG;
    }

    public static function matches(?string $input): bool
    {
        $payload = Session::get(self::SESSION_KEY);

        if (! is_array($payload) || empty($payload['answer']) || ($payload['expires_at'] ?? 0) < now()->timestamp) {
            return false;
        }

        $ok = filled($input) && strtoupper(trim($input)) === strtoupper((string) $payload['answer']);

        // One-time use
        Session::forget(self::SESSION_KEY);

        return $ok;
    }
}
