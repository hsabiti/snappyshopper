<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Postcode;
use App\Models\Store;

class StoresApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_store_requires_api_key_when_configured(): void
    {
        config(['app.api_key' => 'change-me']);

        $this->postJson('/api/stores', [
            'name' => 'Test Store',
            'postcode' => 'SW1A 1AA',
            'delivery_radius_km' => 5,
        ])->assertStatus(401);
    }

    public function test_create_store_with_postcode_resolves_coords(): void
    {
        config(['app.api_key' => 'change-me']);

        Postcode::create(['postcode' => 'SW1A 1AA', 'lat' => 51.501009, 'lng' => -0.141588]);

        $res = $this->withHeader('X-API-KEY', 'change-me')
            ->postJson('/api/stores', [
                'name' => 'Test Store',
                'postcode' => 'SW1A 1AA',
                'delivery_radius_km' => 5,
                'opens_at' => '08:00',
                'closes_at' => '22:00',
            ]);

        $res->assertStatus(201)
            ->assertJsonFragment(['name' => 'Test Store'])
            ->assertJsonStructure(['id','name','postcode','lat','lng']);
    }

    public function test_nearby_requires_origin(): void
    {
        $this->getJson('/api/stores/nearby')
            ->assertStatus(422);
    }

    public function test_nearby_returns_sorted_by_distance(): void
    {
        Store::create([
            'name' => 'Near',
            'postcode' => 'SW1A 1AA',
            'lat' => 51.501009,
            'lng' => -0.141588,
            'delivery_radius_km' => 10,
            'timezone' => 'Europe/London',
        ]);

        Store::create([
            'name' => 'Far',
            'postcode' => 'M1 1AE',
            'lat' => 53.479251,
            'lng' => -2.247926,
            'delivery_radius_km' => 10,
            'timezone' => 'Europe/London',
        ]);

        $res = $this->getJson('/api/stores/nearby?lat=51.501009&lng=-0.141588&radius_km=20');
        $res->assertStatus(200);

        $data = $res->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame('Near', $data[0]['name']);
    }

    public function test_can_deliver_out_of_range(): void
    {
        Postcode::create(['postcode' => 'EC1A 1BB', 'lat' => 51.520180, 'lng' => -0.097790]);

        $store = Store::create([
            'name' => 'Small Radius',
            'postcode' => 'SW1A 1AA',
            'lat' => 51.501009,
            'lng' => -0.141588,
            'delivery_radius_km' => 1.0,
            'timezone' => 'Europe/London',
        ]);

        $res = $this->getJson('/api/stores/can-deliver?store_id='.$store->id.'&postcode=EC1A%201BB');
        $res->assertStatus(200)
            ->assertJsonFragment(['can_deliver' => false])
            ->assertJsonFragment(['reason' => 'OUT_OF_RANGE']);
    }

}
