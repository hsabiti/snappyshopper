<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name','postcode','lat','lng','delivery_radius_km',
        'timezone','opens_at','closes_at'
    ];
}
