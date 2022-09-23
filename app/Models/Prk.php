<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prk extends Model
{
    protected $fillable = [
        'nomor_prk', 'nama_project', 'nomor_lot', 'prioritas', 'is_deleted', 'deleted_at'
    ];

    protected $hidden = [
        'is_deleted', 'deleted_at'
    ];
}
