<?php

namespace App\Http\Controllers;

use App\Models\DeviceReturn;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;

class DeviceReturnPortalController extends Controller
{
    public function index()
    {
        return view('device-returns.portal.index');
    }

    public function create()
    {
        return view('device-returns.portal.create');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_number' => 'required|string|max:255',
            'purchase_date' => 'required|date',
            'device_type' => 'required|in:Kasuari 6G 2S+,Maleo 6G 4S+,Macan 6G 4S+,Komodo 8G 4S+ 2QS28',
            'serial_number' => 'required|string|max:255',
            'include_mikrotik_license' => 'boolean',
            'customer_name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'phone_number' => 'required|string|max:255',
            'issue_details' => 'required|string',
            'proof_files' => 'nullable|array',
            'proof_files.*' => 'file|mimes:jpeg,jpg,png,gif,mp4,mov,avi,mkv,webm|max:10240', // 10MB
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $proofFiles = [];
        if ($request->hasFile('proof_files')) {
            $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
            foreach ($request->file('proof_files') as $file) {
                $path = $file->store('device-returns', $disk);
                $proofFiles[] = $path;
            }
        }

        $deviceReturn = DeviceReturn::create([
            'invoice_number' => $request->invoice_number,
            'purchase_date' => $request->purchase_date,
            'device_type' => $request->device_type,
            'serial_number' => $request->serial_number,
            'include_mikrotik_license' => $request->has('include_mikrotik_license'),
            'customer_name' => $request->customer_name,
            'company_name' => $request->company_name,
            'phone_number' => $request->phone_number,
            'issue_details' => $request->issue_details,
            'proof_files' => $proofFiles,
            'status' => 'pending',
        ]);

        // Create initial log
        $deviceReturn->logs()->create([
            'status' => 'pending',
            'description' => 'Input retur diterima',
            'logged_by' => null,
            'logged_at' => now(),
        ]);

        // Send WhatsApp notification
        $whatsappService = new WhatsAppService();
        $message = "✅ *Retur Perangkat Berhasil Dibuat*\n\n";
        $message .= "Nomor Resi: *{$deviceReturn->tracking_number}*\n";
        $message .= "Jenis Perangkat: {$deviceReturn->device_type}\n";
        $message .= "Serial Number: {$deviceReturn->serial_number}\n";
        $message .= "Invoice: {$deviceReturn->invoice_number}\n\n";
        $message .= "Gunakan nomor resi di atas untuk tracking retur Anda.\n";
        $message .= "Portal: https://retur.digigate.id/tracking";

        $whatsappService->sendMessage($deviceReturn->phone_number, $message);

        return redirect()->route('device-returns.portal.tracking', ['tracking_number' => $deviceReturn->tracking_number])
            ->with('success', 'Retur perangkat berhasil dibuat! Nomor resi: ' . $deviceReturn->tracking_number);
    }

    public function tracking(Request $request)
    {
        $trackingNumber = $request->query('tracking_number');
        
        if (!$trackingNumber) {
            return view('device-returns.portal.tracking', [
                'deviceReturn' => null,
                'trackingNumber' => null,
            ]);
        }

        $deviceReturn = DeviceReturn::where('tracking_number', $trackingNumber)
            ->with('logs.loggedByUser')
            ->first();

        return view('device-returns.portal.tracking', [
            'deviceReturn' => $deviceReturn,
            'trackingNumber' => $trackingNumber,
        ]);
    }
}
