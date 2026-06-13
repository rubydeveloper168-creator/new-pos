<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected $appends = ['image_url'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'sub_unit_ids' => 'array',
    ];

    /**
     * Get the products image.
     *
     * @return string
     */
    public function getImageUrlAttribute()
    {
        if (! empty($this->image)) {
            $image_url = asset('/uploads/img/'.rawurlencode($this->image));
        } else {
            $image_url = asset('/img/default.png');
        }

        return $image_url;
    }

    /**
     * Get the products image path.
     *
     * @return string
     */
    public function getImagePathAttribute()
    {
        if (! empty($this->image)) {
            $image_path = public_path('uploads').'/'.config('constants.product_img_path').'/'.$this->image;
        } else {
            $image_path = null;
        }

        return $image_path;
    }

    public function product_variations()
    {
        return $this->hasMany(\App\ProductVariation::class);
    }

    /**
     * Get the brand associated with the product.
     */
    public function brand()
    {
        return $this->belongsTo(\App\Brands::class);
    }

    /**
     * Get the unit associated with the product.
     */
    public function unit()
    {
        return $this->belongsTo(\App\Unit::class);
    }

    /**
     * Get the unit associated with the product.
     */
    public function second_unit()
    {
        return $this->belongsTo(\App\Unit::class, 'secondary_unit_id');
    }

    /**
     * Get category associated with the product.
     */
    public function category()
    {
        return $this->belongsTo(\App\Category::class);
    }

    /**
     * Get sub-category associated with the product.
     */
    public function sub_category()
    {
        return $this->belongsTo(\App\Category::class, 'sub_category_id', 'id');
    }

    /**
     * Get category Level 1 associated with the product.
     */
    public function categoryL1()
    {
        return $this->belongsTo(\App\Category::class, 'category_l1_id', 'id');
    }

    /**
     * Get category Level 2 associated with the product.
     */
    public function categoryL2()
    {
        return $this->belongsTo(\App\Category::class, 'category_l2_id', 'id');
    }

    /**
     * Get category Level 3 associated with the product.
     */
    public function categoryL3()
    {
        return $this->belongsTo(\App\Category::class, 'category_l3_id', 'id');
    }

    /**
     * Get category Level 4 associated with the product.
     */
    public function categoryL4()
    {
        return $this->belongsTo(\App\Category::class, 'category_l4_id', 'id');
    }

    /**
     * Get category Level 5 associated with the product.
     */
    public function categoryL5()
    {
        return $this->belongsTo(\App\Category::class, 'category_l5_id', 'id');
    }

    /**
     * Get the complete category path for the product
     */
    public function getCategoryPath($separator = ' > ')
    {
        $path = [];
        
        if ($this->categoryL1) $path[] = $this->categoryL1->name;
        if ($this->categoryL2) $path[] = $this->categoryL2->name;
        if ($this->categoryL3) $path[] = $this->categoryL3->name;
        if ($this->categoryL4) $path[] = $this->categoryL4->name;
        if ($this->categoryL5) $path[] = $this->categoryL5->name;
        
        // If no L1-L5 categories are set, fall back to legacy category_id
        if (empty($path) && $this->category) {
            $path[] = $this->category->name;
        }
        
        return implode($separator, $path);
    }

    /**
     * Get the tax associated with the product.
     */
    public function product_tax()
    {
        return $this->belongsTo(\App\TaxRate::class, 'tax', 'id');
    }

    /**
     * Get the variations associated with the product.
     */
    public function variations()
    {
        return $this->hasMany(\App\Variation::class);
    }

    /**
     * If product type is modifier get products associated with it.
     */
    public function modifier_products()
    {
        return $this->belongsToMany(\App\Product::class, 'res_product_modifier_sets', 'modifier_set_id', 'product_id');
    }

    /**
     * If product type is modifier get products associated with it.
     */
    public function modifier_sets()
    {
        return $this->belongsToMany(\App\Product::class, 'res_product_modifier_sets', 'product_id', 'modifier_set_id');
    }

    /**
     * Get the purchases associated with the product.
     */
    public function purchase_lines()
    {
        return $this->hasMany(\App\PurchaseLine::class);
    }

    /**
     * Scope a query to only include active products.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('products.is_inactive', 0);
    }

    /**
     * Scope a query to only include inactive products.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInactive($query)
    {
        return $query->where('products.is_inactive', 1);
    }

    /**
     * Scope a query to only include products for sales.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeProductForSales($query)
    {
        return $query->where('not_for_selling', 0);
    }

    /**
     * Scope a query to only include products not for sales.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeProductNotForSales($query)
    {
        return $query->where('not_for_selling', 1);
    }

    public function product_locations()
    {
        return $this->belongsToMany(\App\BusinessLocation::class, 'product_locations', 'product_id', 'location_id');
    }

    /**
     * Scope a query to only include products available for a location.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForLocation($query, $location_id)
    {
        return $query->where(function ($q) use ($location_id) {
            $q->whereHas('product_locations', function ($query) use ($location_id) {
                $query->where('product_locations.location_id', $location_id);
            });
        });
    }

    /**
     * Get warranty associated with the product.
     */
    public function warranty()
    {
        return $this->belongsTo(\App\Warranty::class);
    }

    public function media()
    {
        return $this->morphMany(\App\Media::class, 'model');
    }

    public function rack_details()
    {
        return $this->hasMany(\App\ProductRack::class);
    }

    /**
     * Get the group types for this product.
     */
    public function groupTypes()
    {
        return $this->belongsToMany(\App\GroupType::class, 'group_type_products')
                    ->withPivot('sort_order')
                    ->withTimestamps();
    }

    /**
     * Get the group sub types for this product.
     */
    public function groupSubTypes()
    {
        return $this->belongsToMany(\App\GroupSubType::class, 'group_sub_type_products')
                    ->withPivot('sort_order')
                    ->withTimestamps();
    }
}
