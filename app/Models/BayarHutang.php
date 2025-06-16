<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BayarHutang extends Model
{
    protected $guarded = [];
    protected $with = ['hutang'];

    public function hutang()
    {
        return $this->belongsTo(Hutang::class);
    }
}
