<?php

namespace Tests\Unit;

use App\Services\GeoService;
use PHPUnit\Framework\TestCase;

class GeoServiceTest extends TestCase
{
    public function test_haversine_distance_is_reasonable(): void
    {
        $geo = new GeoService();

        $km = $geo->haversineKm(51.501009, -0.141588, 51.520180, -0.097790);

        $this->assertGreaterThan(2.0, $km);
        $this->assertLessThan(6.0, $km);
    }

    public function test_eta_increases_with_distance(): void
    {
        $geo = new GeoService();

        $etaShort = $geo->estimateEtaMinutes(1.0);
        $etaLong  = $geo->estimateEtaMinutes(10.0);

        $this->assertGreaterThan(0, $etaShort);
        $this->assertGreaterThan($etaShort, $etaLong);
    }
}
