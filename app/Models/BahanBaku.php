<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BahanBaku extends Model
{
    protected $guarded = [];
    protected $with = ['satuan'];

    public function satuan()
    {
        return $this->belongsTo(Satuan::class);
    }

    public function produkBahan()
    {
        return $this->hasMany(ProdukBahan::class);
    }
}
