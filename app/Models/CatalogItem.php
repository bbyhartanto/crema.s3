<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogItem extends Model
{
    use HasFactory;

    protected $table = 'catalog_items';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'store_id',
        'store_name',
        'store_slug',
        'name',
        'slug',
        'description',
        'origin',
        'roast_level',
        'process',
        'flavor_notes',
        'field_values',
        'marketplace_links',
        'price_min',
        'price_max',
        'image_url',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'flavor_notes' => 'array',
            'field_values' => 'array',
            'marketplace_links' => 'array',
            'price_min' => 'float',
            'price_max' => 'float',
            'is_available' => 'boolean',
        ];
    }
}
