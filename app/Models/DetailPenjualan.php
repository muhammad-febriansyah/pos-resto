<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailPenjualan extends Model
{
    protected $guarded = [
        'id'
    ];
    protected $with = [
        'produk',
        'penjualan',
    ];


    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class);
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    public function getSubtotalItemAttribute()
    {
        return $this->qty * ($this->produk->harga_jual ?? 0);
    }
}
