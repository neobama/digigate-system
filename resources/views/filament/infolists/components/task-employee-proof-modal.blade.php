@php
    $employees = $employees ?? [];
    $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
@endphp

@if(count($employees) > 0)
    <div class="space-y-4 max-h-96 overflow-y-auto">
        @foreach($employees as $employee)
            @php
                $proofImagesRaw = $employee->pivot->proof_images ?? [];
                if (is_string($proofImagesRaw)) {
                    $proofImages = json_decode($proofImagesRaw, true) ?? [];
                } elseif (is_array($proofImagesRaw)) {
                    $proofImages = $proofImagesRaw;
                } else {
                    $proofImages = [];
                }
                $hasProof = !empty($proofImages) && is_array($proofImages) && count($proofImages) > 0;
                $proofUploadedAt = $employee->pivot->proof_uploaded_at ?? null;
                $notes = $employee->pivot->notes ?? null;
            @endphp
            
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="font-semibold text-gray-900 dark:text-gray-100">{{ $employee->name }}</h4>
                    @if($hasProof)
                        <span class="px-2 py-1 text-xs rounded bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                            ✓ Sudah Submit ({{ count($proofImages) }} foto)
                        </span>
                    @else
                        <span class="px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">
                            ⏳ Belum Submit
                        </span>
                    @endif
                </div>

                @if($hasProof)
                    @if($proofUploadedAt)
                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                            Diupload: {{ \Carbon\Carbon::parse($proofUploadedAt)->format('d/m/Y H:i') }}
                        </p>
                    @endif
                    @if($notes)
                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">
                            <strong>Catatan:</strong> {{ $notes }}
                        </p>
                    @endif
                    <div class="grid grid-cols-3 gap-2">
                        @foreach($proofImages as $image)
                            <a href="{{ \Illuminate\Support\Facades\Storage::disk($disk)->url($image) }}" target="_blank" class="block">
                                <img 
                                    src="{{ \Illuminate\Support\Facades\Storage::disk($disk)->url($image) }}" 
                                    alt="Bukti {{ $employee->name }}"
                                    class="w-full h-24 object-cover rounded cursor-pointer hover:opacity-90 transition-opacity"
                                />
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada bukti yang diupload</p>
                @endif
            </div>
        @endforeach
    </div>
@else
    <p class="text-gray-500 dark:text-gray-400">Tidak ada karyawan yang ditugaskan</p>
@endif
