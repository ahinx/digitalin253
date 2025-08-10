<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // Jika Anda ingin relasi ke Order

class Voucher extends Model
{
    protected $fillable = [
        'code',
        'discount_type',
        'value',
        'expires_at',
        'usage_limit',
        'used_count',
    ];

    protected $casts = [
        'value' => 'integer', // Karena decimal(X,0)
        'expires_at' => 'datetime',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
    ];

    /**
     * Relasi ke Order yang menggunakan voucher ini.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
