<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ShopCart extends Model
{
    protected $fillable=['goods_id','user_id','amount'];
}
