<?php

namespace Modules\Region\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Region extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'citCode',
        'citName',
        'staCode',
        // Lista de atributos llenables
    ];

    protected $hidden = [
        // Lista de atributos ocultos
    ];

    public function scopeFilter($query, array $filters): void
    {
        $query->when($filters['query'] ?? null, function ($q, $search) {
            $q->where(function ($query) use ($search) {
                $query->where('citCode', 'like', "%{$search}%")
                      ->orWhere('citName', 'like', "%{$search}%")
                      ->orWhere('staCode', 'like', "%{$search}%");
            });
        });
    }
}
