<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'category_id',
        'brand_id',
        'amount'
    ];

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($product) {
            if ($product->isDirty('price')) {
                $product->updateRelatedOrders();
            }
        });
    }

    public function updateRelatedOrders()
    {
        foreach ($this->orders as $order) {
            $order->recalculateTotalAmount();
        }
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class)->withPivot('quantity');
    }
}