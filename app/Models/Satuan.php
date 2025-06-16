<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Satuan extends Model
{
    protected $guarded = [];

    public function bahanBaku()
    {
        return $this->hasMany(BahanBaku::class);
    }

    public function produk()
    {
        return $this->hasMany(Produk::class);
    }

    public function variant()
    {
        return $this->hasMany(Variant::class);
    }
}
