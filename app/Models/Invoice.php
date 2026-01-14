<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasUuids;
    protected $fillable = ['invoice_number', 'client_name', 'po_number', 'invoice_date', 'paid_date', 'total_amount', 'items', 'shipping_cost', 'discount', 'status'];

    protected $casts = [
        'items' => 'array',
        'discount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'invoice_date' => 'date',
        'paid_date' => 'date',
    ];

    protected static function booted()
    {
        static::saving(function ($invoice) {
            // Auto-calculate total_amount from items, discount, and shipping_cost
            $subtotal = 0;
            if ($invoice->items) {
                foreach ($invoice->items as $item) {
                    $subtotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                }
            }
            
            $discount = $invoice->discount ?? 0;
            $shippingCost = $invoice->shipping_cost ?? 0;
            $invoice->total_amount = $subtotal - $discount + $shippingCost;
            
            // Auto-set paid_date when status changes to paid or delivered
            if (in_array($invoice->status, ['paid', 'delivered']) && !$invoice->paid_date) {
                $invoice->paid_date = now();
            }
            
            // Clear paid_date if status changes back to proforma or cancelled
            if (in_array($invoice->status, ['proforma', 'cancelled']) && $invoice->paid_date) {
                $invoice->paid_date = null;
            }
        });
    }

    public function assemblies()
    {
        return $this->hasMany(Assembly::class);
    }
}