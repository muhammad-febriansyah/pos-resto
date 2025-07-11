<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penjualan extends Model
{
    protected $guarded = [];

    // protected $with = [
    //     'user',
    //     'customer',
    //     'meja',
    //     'details',
    // ];

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function meja()
    {
        return $this->belongsTo(Meja::class);
    }

    public function details()
    {
        return $this->hasMany(DetailPenjualan::class);
    }

    public function rating()
    {
        return $this->hasMany(Rating::class);
    }
}
