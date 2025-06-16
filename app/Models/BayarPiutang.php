<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BayarPiutang extends Model
{
    protected $guarded = [];
    protected $with = ['piutang'];

    public function piutang()
    {
        return $this->belongsTo(Piutang::class);
    }
}
