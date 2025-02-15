<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlotCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'image','multipliers'];

    public function slots()
    {
        return $this->hasMany(Slot::class, 'category_id');
    }
}
