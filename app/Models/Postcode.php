<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Postcode extends Model
{
    use HasFactory;

    protected $fillable = ['postcode', 'lat', 'lng'];

    public static function normalize(string $postcode): string
    {
        $p = strtoupper(trim($postcode));
        $p = preg_replace('/\s+/', ' ', $p) ?? $p;
        return $p;
    }
}
