<?php

// app/Models/Product.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'type',
        'main_image',
        'price',
        'discount_price',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_image_alt',
        'downloadable_type',
        'file_path',
        'external_url',
    ];

    protected $casts = [
        'main_image' => 'array', // ['large' => ..., 'thumb' => ...]
    ];

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
