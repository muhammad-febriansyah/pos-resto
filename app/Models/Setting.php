<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    public static function getAppSetting()
    {
        return self::firstOrCreate(
            [],
            [
                'site_name' => 'Default App Name',
                'ppn' => 0,
                'biaya_layanan_default' => 0,
            ]
        );
    }
}
