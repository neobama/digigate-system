<?php

namespace App\Http\Controllers;

use App\Services\WarrantyClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            'client_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:30'],
            'device_type' => ['required', 'in:'.implode(',', array_keys(WarrantyClaimService::DEVICE_TYPES))],
            'assembly_serial_number' => ['required', 'string', 'max:100'],
            'complaint' => ['required', 'string', 'max:5000'],
        ], [
            'invoice_number.required' => 'Nomor invoice wajib diisi.',
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
            ->with('is_assembly_linked', $claim->is_assembly_linked);
    }

    public function success()
    {
        if (! session()->has('ticket_number')) {
            return redirect()->route('repair.portal.create');
        }

        return view('repair.portal.success', [
            'ticketNumber' => session('ticket_number'),
            'isAssemblyLinked' => (bool) session('is_assembly_linked'),
        ]);
    }
}
