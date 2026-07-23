<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceClick extends Model
{
    use HasFactory;

    protected $table = 'marketplace_clicks';

    protected $fillable = [
        'product_id',
        'target',
        'tenant_id',
        'ip_address',
        'user_agent',
        'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }
}
