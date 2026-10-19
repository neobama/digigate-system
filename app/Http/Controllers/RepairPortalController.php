<?php

namespace App\Http\Controllers;

use App\Services\RepairPortalCaptcha;
use App\Services\WarrantyClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RepairPortalController extends Controller
{
    public function create()
    {
        RepairPortalCaptcha::regenerate();

        return view('repair.portal.create', [
            'deviceTypes' => WarrantyClaimService::DEVICE_TYPES,
            'captchaSvg' => RepairPortalCaptcha::svg(),
        ]);
    }

    public function captcha()
    {
        RepairPortalCaptcha::regenerate();

        return response(RepairPortalCaptcha::svg(), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function store(Request $request)
    {
        // Honeypot: bot yang isi field tersembunyi ditolak diam-diam.
        if (filled($request->input('company_website'))) {
            return redirect()->route('repair.portal.create');
        }

        $validator = Validator::make($request->all(), [
            'invoice_number' => ['required', 'string', 'max:100'],
            'invoice_date' => ['required', 'date', 'before_or_equal:today'],
            'client_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:30'],
            'device_type' => ['required', Rule::in(array_keys(WarrantyClaimService::DEVICE_TYPES))],
            'assembly_serial_number' => ['required', 'string', 'max:100'],
            'complaint' => ['required', 'string', 'max:5000'],
            'captcha' => ['required', 'string', 'max:10'],
        ], [
            'invoice_number.required' => 'Nomor invoice wajib diisi.',
            'invoice_date.required' => 'Tanggal invoice wajib diisi.',
            'invoice_date.before_or_equal' => 'Tanggal invoice tidak boleh di masa depan.',
            'client_name.required' => 'Nama perusahaan/perorangan wajib diisi.',
            'phone_number.required' => 'Nomor telepon wajib diisi.',
            'device_type.required' => 'Pilih nama perangkat.',
            'assembly_serial_number.required' => 'Serial number wajib diisi.',
            'complaint.required' => 'Keluhan wajib diisi.',
            'captcha.required' => 'Kode captcha wajib diisi.',
        ]);

        $validator->after(function ($validator) use ($request) {
            if (! RepairPortalCaptcha::matches($request->input('captcha'))) {
                $validator->errors()->add('captcha', 'Kode captcha salah atau sudah kedaluwarsa. Silakan coba lagi.');
            }
        });

        if ($validator->fails()) {
            RepairPortalCaptcha::regenerate();

            return back()->withErrors($validator)->withInput();
        }

        $claim = WarrantyClaimService::createFromClientPortal($validator->safe()->except(['captcha']));

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
