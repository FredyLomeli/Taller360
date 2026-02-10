<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleHistory extends Model
{
    protected $fillable = ['sale_id', 'user_id', 'from_stage', 'to_stage', 'notes'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
