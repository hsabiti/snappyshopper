<?php

namespace App\Http\Controllers;

use App\Models\Postcode;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StoreController extends Controller
{
    // POST /api/stores
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'postcode' => ['required','string','max:16'],
            'lat' => ['nullable','numeric','between:-90,90'],
            'lng' => ['nullable','numeric','between:-180,180'],
            'delivery_radius_km' => ['nullable','numeric','min:0.1','max:100'],
            'timezone' => ['nullable','string','max:64'],
            'opens_at' => ['nullable','date_format:H:i'],
            'closes_at' => ['nullable','date_format:H:i'],
        ]);

        $postcode = Postcode::normalize($data['postcode']);

        // Resolve coords if not provided, using cached postcode lookup
        if (!isset($data['lat'], $data['lng'])) {
            $coords = $this->coordsFromPostcode($postcode);
            if (!$coords) {
                return response()->json([
                    'message' => 'Postcode not found in dataset',
                ], 422);
            }
            $data['lat'] = $coords['lat'];
            $data['lng'] = $coords['lng'];
        }

        $store = Store::create([
            'name' => $data['name'],
            'postcode' => $postcode,
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'delivery_radius_km' => $data['delivery_radius_km'] ?? 5.0,
            'timezone' => $data['timezone'] ?? 'Europe/London',
            'opens_at' => $data['opens_at'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
        ]);

        return response()->json($store, 201);
    }

    // GET /api/stores/nearby?lat=..&lng=.. OR ?postcode=..
    public function nearby(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['nullable','numeric','between:-90,90'],
            'lng' => ['nullable','numeric','between:-180,180'],
            'postcode' => ['nullable','string','max:16'],
            'radius_km' => ['nullable','numeric','min:0.1','max:200'],
            'per_page' => ['nullable','integer','min:1','max:100'],
        ]);

        $radiusKm = (float)($data['radius_km'] ?? 5.0);
        $perPage = (int)($data['per_page'] ?? 20);

        // Resolve origin coords
        if (isset($data['postcode'])) {
            $postcode = Postcode::normalize($data['postcode']);
            $coords = $this->coordsFromPostcode($postcode);
            if (!$coords) {
                return response()->json(['message' => 'Postcode not found in dataset'], 422);
            }
            $lat = $coords['lat'];
            $lng = $coords['lng'];
        } else {
            if (!isset($data['lat'], $data['lng'])) {
                return response()->json(['message' => 'Provide (lat,lng) or postcode'], 422);
            }
            $lat = (float)$data['lat'];
            $lng = (float)$data['lng'];
        }

        // Bounding box prefilter (fast)
        [$minLat, $maxLat, $minLng, $maxLng] = $this->boundingBox($lat, $lng, $radiusKm);

        $candidates = Store::query()
            ->whereBetween('lat', [$minLat, $maxLat])
            ->whereBetween('lng', [$minLng, $maxLng])
            ->select(['id','name','postcode','lat','lng','delivery_radius_km','opens_at','closes_at','timezone'])
            ->get();

        // Compute distance in PHP (portable), then filter + sort
        $stores = $candidates
            ->map(function (Store $s) use ($lat, $lng) {
                $dist = $this->haversineKm($lat, $lng, (float)$s->lat, (float)$s->lng);
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'postcode' => $s->postcode,
                    'lat' => (float)$s->lat,
                    'lng' => (float)$s->lng,
                    'delivery_radius_km' => (float)$s->delivery_radius_km,
                    'distance_km' => $dist,
                ];
            })
            ->filter(fn($s) => $s['distance_km'] <= $radiusKm)
            ->sortBy('distance_km')
            ->values();

        // Simple manual pagination (we used collection)
        $page = max(1, (int)$request->query('page', 1));
        $total = $stores->count();
        $items = $stores->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'radius_km' => $radiusKm,
            ],
        ]);
    }

    // GET /api/stores/can-deliver?store_id=1&postcode=... OR &lat=..&lng=..
    public function canDeliver(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id' => ['required','integer','min:1'],
            'lat' => ['nullable','numeric','between:-90,90'],
            'lng' => ['nullable','numeric','between:-180,180'],
            'postcode' => ['nullable','string','max:16'],
        ]);

        $store = Store::find($data['store_id']);
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        // Resolve destination coords
        if (isset($data['postcode'])) {
            $postcode = Postcode::normalize($data['postcode']);
            $coords = $this->coordsFromPostcode($postcode);
            if (!$coords) {
                return response()->json(['message' => 'Postcode not found in dataset'], 422);
            }
            $destLat = $coords['lat'];
            $destLng = $coords['lng'];
        } else {
            if (!isset($data['lat'], $data['lng'])) {
                return response()->json(['message' => 'Provide (lat,lng) or postcode'], 422);
            }
            $destLat = (float)$data['lat'];
            $destLng = (float)$data['lng'];
        }

        $distanceKm = $this->haversineKm((float)$store->lat, (float)$store->lng, $destLat, $destLng);
        $withinRadius = $distanceKm <= (float)$store->delivery_radius_km;

        // Bonus: operating hours check
        $open = $this->isStoreOpenNow($store);

        $canDeliver = $withinRadius && $open;

        $etaMinutes = $this->estimateEtaMinutes($distanceKm);

        return response()->json([
            'store_id' => $store->id,
            'can_deliver' => $canDeliver,
            'distance_km' => round($distanceKm, 3),
            'store_radius_km' => (float)$store->delivery_radius_km,
            'is_open' => $open,
            'eta_minutes' => $etaMinutes,
            'reason' => $canDeliver ? null : ($open ? 'OUT_OF_RANGE' : 'STORE_CLOSED'),
        ]);
    }

    private function coordsFromPostcode(string $postcode): ?array
    {
        $key = 'pc:' . $postcode;

        return Cache::remember($key, now()->addDay(), function () use ($postcode) {
            $pc = Postcode::query()->where('postcode', $postcode)->first();
            if (!$pc) return null;
            return ['lat' => (float)$pc->lat, 'lng' => (float)$pc->lng];
        });
    }

    private function isStoreOpenNow(Store $store): bool
    {
        if (!$store->opens_at || !$store->closes_at) {
            return true; // treat as always open if not configured
        }

        $tz = $store->timezone ?: 'Europe/London';
        $now = Carbon::now($tz);

        $open = Carbon::createFromFormat('H:i:s', (string)$store->opens_at, $tz);
        $close = Carbon::createFromFormat('H:i:s', (string)$store->closes_at, $tz);

        // handle overnight close (e.g. 22:00 -> 02:00)
        if ($close->lessThanOrEqualTo($open)) {
            return $now->gte($open) || $now->lte($close);
        }

        return $now->between($open, $close);
    }

    private function estimateEtaMinutes(float $distanceKm): int
    {
        // Minimal heuristic: 10 mins handling + travel time @ 20km/h average
        $handling = 10;
        $travel = (int)ceil(($distanceKm / 20.0) * 60.0);
        return $handling + $travel;
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
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

    private function boundingBox(float $lat, float $lng, float $radiusKm): array
    {
        // ~111km per degree latitude
        $latDelta = $radiusKm / 111.0;

        // longitude delta depends on latitude
        $lngDelta = $radiusKm / (111.0 * max(cos(deg2rad($lat)), 0.01));

        return [
            $lat - $latDelta,
            $lat + $latDelta,
            $lng - $lngDelta,
            $lng + $lngDelta,
        ];
    }
}
