<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 
        'business_name', 
        'price_tier',
        'email', 
        'phones',
        'street_address', 
        'neighborhood', 
        'city', 
        'state', 
        'delegation', 
        'zip_code',
        'references'
    ];
}
