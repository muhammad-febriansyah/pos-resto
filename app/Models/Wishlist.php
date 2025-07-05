<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    protected $guarded = [];
    protected $with = ['produk'];

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }
}
