<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('customer', 'products')->paginate(10);
        return view('orders.index', compact('orders'));
    }

    public function create()
    {
        $customers = Customer::all();
        $products = Product::where('amount', '>', 0)->get();
        return view('orders.create', compact('customers', 'products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'products' => 'required|array',
            'products.*' => 'exists:products,id',
            'quantities' => 'required|array',
            'quantities.*' => 'integer|min:1',
        ]);

        $order = Order::create([
            'customer_id' => $request->customer_id,
            'total_amount' => 0,
        ]);

        foreach ($request->products as $index => $productId) {
            $quantity = $request->quantities[$productId] ?? 0;
            $order->products()->attach($productId, ['quantity' => $quantity]);
        }

        $order->recalculateTotalAmount();

        return redirect()->route('orders.index')->with('success', 'Order created successfully.');
    }

    public function show(Order $order)
    {
        $order->load('customer', 'products');
        $order->recalculateTotalAmount();
        return view('orders.show', compact('order'));
    }

    public function edit(Order $order)
    {
        $customers = Customer::all();
        $products = Product::where('amount', '>', 0)
            ->orWhereHas('orders', function ($query) use ($order) {
                $query->where('order_id', $order->id);
            })
            ->get();
        $order->load('products');
        return view('orders.edit', compact('order', 'customers', 'products'));
    }

    public function update(Request $request, Order $order)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'products' => 'required|array',
            'products.*' => 'exists:products,id',
            'quantities' => 'required|array',
            'quantities.*' => 'integer|min:1',
        ]);

        DB::transaction(function () use ($request, $order) {
            $order->update([
                'customer_id' => $request->customer_id,
                'total_amount' => 0,
            ]);

            foreach ($order->products as $product) {
                $product->increment('amount', $product->pivot->quantity);
            }

            $order->products()->detach();

            $totalAmount = 0;

            foreach ($request->products as $productId) {
                $product = Product::findOrFail($productId);
                $quantity = $request->quantities[$productId];

                if ($quantity > $product->amount) {
                    throw new \Exception("Not enough stock for product: {$product->name}");
                }

                $order->products()->attach($productId, ['quantity' => $quantity]);
                $totalAmount += $product->price * $quantity;

                $product->decrement('amount', $quantity);
            }

            $order->update(['total_amount' => $totalAmount]);
        });

        return redirect()->route('orders.index')->with('success', 'Order updated successfully.');
    }

    public function destroy(Order $order)
    {
        DB::transaction(function () use ($order) {
            foreach ($order->products as $product) {
                $product->increment('amount', $product->pivot->quantity);
            }
            $order->products()->detach();
            $order->delete();
        });
        
        return redirect()->route('orders.index')->with('success', 'Order deleted successfully.');
    }
}