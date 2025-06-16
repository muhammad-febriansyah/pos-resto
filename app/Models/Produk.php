<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Produk extends Model
{
    protected $guarded = [];
    protected $with = ['kategori', 'satuan'];

    public function kategori()
    {
        return $this->belongsTo(Kategori::class);
    }
    public function satuan()
    {
        return $this->belongsTo(Satuan::class);
    }
    public function produkBahan()
    {
        return $this->hasMany(ProdukBahan::class);
    }

    public function details()
    {
        return $this->hasMany(DetailPenjualan::class);
    }

    public function penjualan()
    {
        return $this->hasMany(Penjualan::class, 'produk_id');
    }
}
