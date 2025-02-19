<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SlotCategory extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['name', 'image', 'multipliers', 'status'];

    public function slots()
    {
        return $this->hasMany(Slot::class, 'category_id');
    }
}
