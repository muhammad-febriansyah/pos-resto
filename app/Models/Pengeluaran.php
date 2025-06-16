<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengeluaran extends Model
{
    protected $guarded = [];
    protected $with = ['kategori_catatan', 'user'];

    public function kategori_catatan()
    {
        return $this->belongsTo(KategoriCatatan::class, 'kategori_catatan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
