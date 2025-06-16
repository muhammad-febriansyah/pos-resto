<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KategoriCatatan extends Model
{
    protected $guarded = [];

    public function pengeluaran()
    {
        return $this->hasMany(Pengeluaran::class);
    }

    public function pemasukan()
    {
        return $this->hasMany(Pemasukan::class);
    }
}
