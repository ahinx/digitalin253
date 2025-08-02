<?php

// app/Models/ProductVariant.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'price',
        'image',
        'downloadable_type',
        'file_path',
        'external_url',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
