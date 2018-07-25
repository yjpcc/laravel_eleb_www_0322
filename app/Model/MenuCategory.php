<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class MenuCategory extends Model
{
    public function menu(){
        return $this->hasMany(Menu::class, 'category_id', 'id');
    }
}
