<?php

namespace App\Services;

class AttendanceLocationService
{
    public function officeLatitude(): float
    {
        return (float) config('attendance.office_latitude');
    }

    public function officeLongitude(): float
    {
        return (float) config('attendance.office_longitude');
    }

    public function radiusMeters(): int
    {
        $radius = (int) config('attendance.radius_meters');

        return max(
            (int) config('attendance.min_radius_meters'),
            min($radius, (int) config('attendance.max_radius_meters'))
        );
    }

    /**
     * Hitung jarak dalam meter antara dua koordinat (Haversine).
     */
    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $latFrom = deg2rad($lat1);
        $latTo = deg2rad($lat2);
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    public function isWithinRadius(float $latitude, float $longitude): bool
    {
        $distance = $this->distanceMeters(
            $latitude,
            $longitude,
            $this->officeLatitude(),
            $this->officeLongitude()
        );

        return $distance <= $this->radiusMeters();
    }

    /**
     * @return array{distance_meters: float, is_within_radius: bool}
     */
    public function evaluate(float $latitude, float $longitude): array
    {
        $distance = $this->distanceMeters(
            $latitude,
            $longitude,
            $this->officeLatitude(),
            $this->officeLongitude()
        );

        return [
            'distance_meters' => $distance,
            'is_within_radius' => $distance <= $this->radiusMeters(),
        ];
    }
}
