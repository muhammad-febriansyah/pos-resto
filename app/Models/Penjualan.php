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

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id'); // Asumsi customer juga dari tabel users
    }

    public function meja()
    {
        return $this->belongsTo(Meja::class);
    }

    public function details()
    {
        return $this->hasMany(DetailPenjualan::class);
    }
}
