<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recipient_name',
        'phone_number',
        'province_id',
        'city_id',
        'district_id',
        'postal_code',
        'full_address',
        'is_primary',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
