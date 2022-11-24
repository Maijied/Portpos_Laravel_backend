<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Refund extends Model
{
    use HasFactory;
    use SoftDeletes;

    public function oderDetails()
    {
        return $this->belongsTo('App\Models\OrderManagement', 'order_id', 'id');
    }
}
