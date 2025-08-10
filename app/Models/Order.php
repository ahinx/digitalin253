<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'discount_amount',
        'voucher_id',
        'payment_info',
    ];

    protected $casts = [
        'payment_info' => 'array',
        'total_price' => 'integer', // Sesuai decimal(X,0)
        'discount_amount' => 'integer', // Sesuai decimal(X,0)
        'tracking_key' => 'string',
        'created_at' => 'datetime', // Penting untuk tampilan dateTime() di Filament
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relasi ke Order Items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Relasi ke Voucher yang digunakan.
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
