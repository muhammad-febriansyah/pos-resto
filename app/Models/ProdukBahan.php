<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdukBahan extends Model
{
    protected $guarded = [];
    protected $with = ['produk', 'bahanBaku'];

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    public function bahanBaku()
    {
        return $this->belongsTo(BahanBaku::class);
    }
}
