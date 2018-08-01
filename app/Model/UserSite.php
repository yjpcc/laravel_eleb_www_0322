<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class UserSite extends Model
{
    protected $fillable=['user_id','province','city','county','address','tel','name','is_default'];
}
