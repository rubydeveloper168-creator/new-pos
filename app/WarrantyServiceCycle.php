<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WarrantyServiceCycle extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected $casts = [
        'notified_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function sellLine()
    {
        return $this->belongsTo(\App\TransactionSellLine::class, 'sell_line_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(\App\User::class, 'updated_by');
    }
}
