<?php

namespace App\Http\Controllers;

use App\Services\WarrantyClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RepairPortalController extends Controller
{
    public function create()
    {
        return view('repair.portal.create', [
            'deviceTypes' => WarrantyClaimService::DEVICE_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_number' => ['required', 'string', 'max:100'],
            'invoice_date' => ['required', 'date', 'before_or_equal:today'],
            'client_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:30'],
            'device_type' => ['required', Rule::in(array_keys(WarrantyClaimService::DEVICE_TYPES))],
            'assembly_serial_number' => ['required', 'string', 'max:100'],
            'complaint' => ['required', 'string', 'max:5000'],
        ], [
            'invoice_number.required' => 'Nomor invoice wajib diisi.',
            'invoice_date.required' => 'Tanggal invoice wajib diisi.',
            'invoice_date.before_or_equal' => 'Tanggal invoice tidak boleh di masa depan.',
            'client_name.required' => 'Nama perusahaan/perorangan wajib diisi.',
            'phone_number.required' => 'Nomor telepon wajib diisi.',
            'device_type.required' => 'Pilih nama perangkat.',
            'assembly_serial_number.required' => 'Serial number wajib diisi.',
            'complaint.required' => 'Keluhan wajib diisi.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $claim = WarrantyClaimService::createFromClientPortal($validator->validated());

        return redirect()
            ->route('repair.portal.success')
            ->with('ticket_number', $claim->ticket_number)
            ->with('claim_category', $claim->claim_category)
            ->with('phone_number', $claim->phone_number);
    }

    public function success()
    {
        if (! session()->has('ticket_number')) {
            return redirect()->route('repair.portal.create');
        }

        return view('repair.portal.success', [
            'ticketNumber' => session('ticket_number'),
            'claimCategory' => session('claim_category'),
            'phoneNumber' => session('phone_number'),
        ]);
    }
}
