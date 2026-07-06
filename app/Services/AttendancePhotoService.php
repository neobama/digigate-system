<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttendancePhotoService
{
    public function disk(): string
    {
        return config('filesystems.default') === 's3' ? 's3_public' : 'public';
    }

    /**
     * Tambahkan timestamp + koordinat ke foto selfie, simpan kembali ke storage.
     */
    public function stampPhoto(string $path, Carbon $recordedAt, float $latitude, float $longitude): string
    {
        $disk = Storage::disk($this->disk());
        $contents = $disk->get($path);

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return $path;
        }

        $timestamp = $recordedAt->timezone('Asia/Jakarta')->format('d/m/Y H:i:s').' WIB';
        $coords = sprintf('Lat: %.6f, Lng: %.6f', $latitude, $longitude);
        $overlayText = $timestamp."\n".$coords;

        $this->drawOverlay($image, $overlayText);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg');
        $stampedPath = 'attendances/stamped/'.Str::uuid().'.'.$extension;

        ob_start();
        match ($extension) {
            'png' => imagepng($image, null, 8),
            'gif' => imagegif($image),
            default => imagejpeg($image, null, 90),
        };
        $stampedContents = ob_get_clean();
        imagedestroy($image);

        $disk->put($stampedPath, $stampedContents, 'public');

        if ($path !== $stampedPath && $disk->exists($path)) {
            $disk->delete($path);
        }

        return $stampedPath;
    }

    private function drawOverlay(\GdImage $image, string $text): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $lines = explode("\n", $text);
        $lineHeight = 18;
        $padding = 12;
        $boxHeight = (count($lines) * $lineHeight) + ($padding * 2);
        $top = max(0, $height - $boxHeight);

        $background = imagecolorallocatealpha($image, 0, 0, 0, 40);
        imagefilledrectangle($image, 0, $top, $width, $height, $background);

        $white = imagecolorallocate($image, 255, 255, 255);

        foreach ($lines as $index => $line) {
            imagestring(
                $image,
                4,
                $padding,
                $top + $padding + ($index * $lineHeight),
                $line,
                $white
            );
        }
    }
}
