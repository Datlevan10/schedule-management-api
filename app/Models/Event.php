<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'start_date',
        'end_date',
        'location',
        'status',
        'category_id',
        'max_participants',
        'meta_data'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'meta_data' => 'array'
    ];

    public function category()
    {
        return $this->belongsTo(EventCategory::class);
    }
}
