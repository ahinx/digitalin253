<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'deliverable_id',
        'deliverable_type',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function deliverable(): MorphTo
    {
        return $this->morphTo();
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // public function variant()
    // {
    //     return $this->belongsTo(ProductVariant::class);
    // }

    public function variant()
    {
        return $this->belongsTo(\App\Models\ProductVariant::class, 'product_variant_id');
    }
}
