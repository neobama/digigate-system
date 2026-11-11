<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'invoice_number',
        'client_name',
        'po_number',
        'invoice_date',
        'paid_date',
        'total_amount',
        'amount_paid',
        'items',
        'shipping_cost',
        'discount',
        'status',
    ];

    protected $casts = [
        'items' => 'array',
        'discount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
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

            // paid_date / status for payments are primarily managed by InvoicePaymentService.
            // Keep a safety net for legacy manual status flips without payment rows.
            if (in_array($invoice->status, ['paid', 'delivered'], true) && ! $invoice->paid_date) {
                $invoice->paid_date = now();
            }

            if (in_array($invoice->status, ['proforma', 'cancelled'], true) && (float) $invoice->amount_paid <= 0) {
                $invoice->paid_date = null;
            }
        });
    }

    public function assemblies()
    {
        return $this->hasMany(Assembly::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderByDesc('paid_at')->orderByDesc('created_at');
    }

    public function income(): HasOne
    {
        return $this->hasOne(Income::class);
    }

    public function remainingAmount(): float
    {
        return max(0, round((float) $this->total_amount - (float) $this->amount_paid, 2));
    }

    public function isFullyPaid(): bool
    {
        return $this->remainingAmount() <= 0.009;
    }

    public function isPartiallyPaid(): bool
    {
        return (float) $this->amount_paid > 0 && ! $this->isFullyPaid();
    }

    public function expectedAssemblyCount(): int
    {
        $total = collect($this->items ?? [])
            ->sum(fn (array $item) => max(1, (int) ($item['quantity'] ?? 1)));

        return max(1, $total);
    }

    public function assemblyCount(): int
    {
        if (isset($this->assemblies_count)) {
            return (int) $this->assemblies_count;
        }

        return $this->assemblies()->count();
    }

    public function remainingAssemblySlots(): int
    {
        return max(0, $this->expectedAssemblyCount() - $this->assemblyCount());
    }

    public function canCreateMoreAssemblies(): bool
    {
        return $this->remainingAssemblySlots() > 0;
    }

    /**
     * @return array<int, string>
     */
    public static function idsWithAssemblySlotsRemaining(): array
    {
        return static::query()
            ->whereIn('status', ['paid', 'delivered'])
            ->withCount('assemblies')
            ->orderByDesc('invoice_date')
            ->get()
            ->filter(fn (self $invoice) => $invoice->canCreateMoreAssemblies())
            ->pluck('id')
            ->all();
    }
}
