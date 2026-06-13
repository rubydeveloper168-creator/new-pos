<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GroupSubType extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Get the group type that owns this sub type.
     */
    public function groupType()
    {
        return $this->belongsTo(\App\GroupType::class);
    }

    /**
     * Get the products for this group sub type.
     */
    public function products()
    {
        return $this->belongsToMany(\App\Product::class, 'group_sub_type_products')
                    ->withPivot('sort_order')
                    ->withTimestamps()
                    ->orderBy('group_sub_type_products.sort_order');
    }

    /**
     * Get the user who created this group sub type.
     */
    public function createdBy()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    /**
     * Scope a query to only include active group sub types.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope a query to order by sort_order.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
