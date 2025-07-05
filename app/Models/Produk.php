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

    public function detailPenjualan()
    {
        return $this->hasMany(DetailPenjualan::class);
    }

    public function penjualan()
    {
        return $this->hasMany(Penjualan::class, 'produk_id');
    }

    public function wishlist()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function rating()
    {
        return $this->hasMany(Rating::class);
    }
}
