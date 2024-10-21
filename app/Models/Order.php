<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'total_amount'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class)->withPivot('quantity');
    }

    public function recalculateTotalAmount()
    {
        $this->load('products');
        $this->total_amount = $this->products->sum(function ($product) {
            return $product->price * $product->pivot->quantity;
        });
        $this->save();
    }

    public function calculateDiscount()
    {
        $totalAmount = $this->total_amount;

        $discount = 0;
        $discountReason = '';

        if ($totalAmount > 10000) {
            $discount = $totalAmount * 0.1; 
            $discountReason = '10% знижка за замовлення понад 10000₴';
        } else {
            $discount = $totalAmount * 0.05;
            $discountReason = '5% знижка за замовлення менше 10000₴';
        }

        if ($discount > 0) {
            return sprintf("%s. Сума знижки: %.2f₴", $discountReason, $discount);
        }

        return "Знижка не застосована";
    }

    public function generateDetailedView()
    {
        $html = '<div class="bg-white shadow overflow-hidden sm:rounded-lg">';
        $html .= '<div class="px-4 py-5 sm:px-6">';
        $html .= '<h3 class="text-lg leading-6 font-medium text-gray-900">Детальний перегляд замовлення #' . $this->id . '</h3>';
        $html .= '</div>';
        $html .= '<div class="border-t border-gray-200">';
        $html .= '<dl>';

        $html .= $this->generateDetailRow('Клієнт', $this->customer->name);
        $html .= $this->generateDetailRow('Email клієнта', $this->customer->email);
        $html .= $this->generateDetailRow('Телефон клієнта', $this->customer->phone);

        $html .= $this->generateDetailRow('Дата замовлення', $this->created_at->format('d.m.Y H:i'));
        $html .= $this->generateDetailRow('Загальна сума', number_format($this->total_amount, 2) . '₴');

        $html .= '<div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">';
        $html .= '<dt class="text-sm font-medium text-gray-500">Товари</dt>';
        $html .= '<dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">';
        $html .= '<ul class="border border-gray-200 rounded-md divide-y divide-gray-200">';

        foreach ($this->products as $product) {
            $html .= '<li class="pl-3 pr-4 py-3 flex items-center justify-between text-sm">';
            $html .= '<div class="w-0 flex-1 flex items-center">';
            $html .= '<span class="ml-2 flex-1 w-0 truncate">' . $product->name . ' x ' . $product->pivot->quantity . '</span>';
            $html .= '</div>';
            $html .= '<div class="ml-4 flex-shrink-0">';
            $html .= '<span class="font-medium">' . number_format($product->pivot->quantity * $product->price, 2) . '₴</span>';
            $html .= '</div>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</dd>';
        $html .= '</div>';

        $html .= $this->generateDetailRow('Інформація про знижку', $this->calculateDiscount());

        $html .= '</dl>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    private function generateDetailRow($label, $value)
    {
        $bgClass = $this->rowCounter % 2 === 0 ? 'bg-gray-50' : 'bg-white';
        $this->rowCounter++;

        return '
            <div class="' . $bgClass . ' px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500">' . $label . '</dt>
                <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">' . $value . '</dd>
            </div>
        ';
    }

    private $rowCounter = 0;
}