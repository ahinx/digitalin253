<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;

class ThankYouController extends Controller
{
    public function show(Request $request)
    {
        $orderId = $request->query('order_id');
        $order = Order::with('items.product', 'items.variant')->findOrFail($orderId);

        return view('thank-you', compact('order'));
    }
}
