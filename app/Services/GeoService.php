<?php

namespace App\Services;

class GeoService
{
    public function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    public function estimateEtaMinutes(float $distanceKm): int
    {
        $handling = 10;
        $travel = (int)ceil(($distanceKm / 20.0) * 60.0);
        return $handling + $travel;
    }
}
