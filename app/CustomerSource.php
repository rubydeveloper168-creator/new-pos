<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerSource extends Model
{
    protected $table = 'customer_sources';

    protected $fillable = [
        'business_id',
        'name',
        'logo_path',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the business that owns the customer source
     */
    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    /**
     * Get active sources for a business
     */
    public static function getActiveForBusiness($business_id)
    {
        return self::where('business_id', $business_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get logo URL
     */
    public function getLogoUrlAttribute()
    {
        if ($this->logo_path) {
            return asset('uploads/customer_sources/' . $this->logo_path);
        }
        return null;
    }
}
