<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['metric_date', 'metric_name', 'source', 'value', 'unit', 'calculated_at', 'dimension_key', 'sample_count'])]
#[Guarded(['id'])]
class DailyMetric extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'value' => 'decimal:4',
            'calculated_at' => 'datetime',
        ];
    }
}
