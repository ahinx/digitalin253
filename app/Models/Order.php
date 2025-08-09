<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'buyer_name',
        'phone',
        'email',
        'magic_link_token',
        'tracking_key',
        'status',
        'total_price',
        'tracking_key',
        'payment_info',
    ];

    // Jika Anda ingin payment_info otomatis di-cast ke array/object
    protected $casts = [
        'payment_info' => 'array',
        'total_price' => 'decimal:0', // Pastikan Laravel tahu ini desimal
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
