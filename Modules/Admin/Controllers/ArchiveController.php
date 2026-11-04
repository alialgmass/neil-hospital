<?php

namespace Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Models\ArchiveUploadSession;
use Modules\Booking\DTOs\BookingData;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Doctor\Models\Doctor;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ArchiveController extends Controller
{
    private const MAX_FILE_KB = 2097152;

    private const ALLOWED_MIMES = 'jpg,jpeg,png,gif,bmp,tif,tiff,webp,pdf,doc,docx,xls,xlsx,mp4,zip';

    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    public function index(): Response
    {
        $filters = request()->only(['search', 'dept', 'from', 'to']);

        $bookings = $this->bookingService->getArchive($filters, 30);

        $bookings->getCollection()->transform(function (Booking $booking) {
            $booking->media_files = $booking->getMedia('archive-files')->map(fn (Media $m) => [
                'id' => $m->id,
                'name' => $m->file_name,
                'url' => $m->getUrl(),
                'mime' => $m->mime_type,
                'size' => $m->human_readable_size,
            ]);

            return $booking;
        });

        return Inertia::render('admin/Archive', [
            'bookings' => $bookings,
            'filters' => $filters,
            'doctors' => Doctor::select('id', 'name')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patient_name' => ['required', 'string', 'max:200'],
            'patient_phone' => ['nullable', 'string', 'max:30'],
            'patient_age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'gender' => ['nullable', 'in:male,female'],
            'dept' => ['required', 'in:clinic,labs,surgery,lasik,laser'],
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'visit_date' => ['required', 'date'],
            'service_name' => ['nullable', 'string', 'max:200'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'pay_method' => ['nullable', 'in:cash,card,transfer,insurance,contract'],
            'visit_note' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:'.self::MAX_FILE_KB, 'mimes:'.self::ALLOWED_MIMES],
        ], self::uploadMessages());

        $price = (float) ($data['price'] ?? 0);
        $paid = (float) ($data['paid_amount'] ?? 0);
        $payStatus = $paid >= $price && $price > 0
            ? PayStatus::Paid
            : ($paid > 0 ? PayStatus::Partial : PayStatus::Unpaid);

        $bookingData = BookingData::fromArray([
            ...$data,
            'price' => $price,
            'paid_amount' => $paid,
            'pay_method' => $data['pay_method'] ?? 'cash',
            'pay_status' => $payStatus->value,
            'status' => 'completed',
        ]);

        $booking = $this->bookingService->create($bookingData, auth()->id());

        foreach ($request->file('files', []) as $file) {
            $booking->addMedia($file)->toMediaCollection('archive-files');
        }

        return redirect()->route('archive')->with('success', 'تم إضافة السجل إلى الأرشيف بنجاح.');
    }

    /**
     * @return array<string, string>
     */
    private static function uploadMessages(): array
    {
        $maxMb = self::MAX_FILE_KB / 1024;

        return [
            'file.max' => "حجم الملف يجب ألا يتجاوز {$maxMb} ميجابايت.",
            'files.*.max' => "حجم الملف يجب ألا يتجاوز {$maxMb} ميجابايت.",
            'file.mimes' => 'نوع الملف غير مدعوم.',
            'files.*.mimes' => 'نوع الملف غير مدعوم.',
            'file.uploaded' => 'فشل رفع الملف، حاول مرة أخرى.',
            'files.*.uploaded' => 'فشل رفع الملف، حاول مرة أخرى.',
        ];
    }

    public function upload(Request $request, Booking $booking): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.self::MAX_FILE_KB, 'mimes:'.self::ALLOWED_MIMES],
        ], self::uploadMessages());

        $booking->addMediaFromRequest('file')->toMediaCollection('archive-files');

        return back()->with('success', 'تم رفع الملف بنجاح.');
    }

    public function initializeChunkedUpload(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            'file_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:120'],
            'file_size' => ['required', 'integer', 'min:1', 'max:'.(self::MAX_FILE_KB * 1024)],
            'chunk_size' => ['required', 'integer', 'min:1048576', 'max:10485760'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $session = ArchiveUploadSession::create([
            ...$data,
            'booking_id' => $booking->id,
            'received_chunks' => [],
        ]);
        Storage::disk('local')->makeDirectory("archive-chunks/{$session->id}");

        return response()->json(['upload_id' => $session->id]);
    }

    public function uploadChunk(Request $request, Booking $booking, ArchiveUploadSession $upload): JsonResponse
    {
        abort_unless($upload->booking_id === $booking->id, 404);

        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0', 'max:9999'],
            'chunk' => ['required', 'file', 'max:10240'],
        ]);

        if ((int) $data['index'] >= $upload->total_chunks) {
            return response()->json(['message' => 'رقم الجزء غير صالح.'], 422);
        }

        $disk = Storage::disk('local');
        $directory = "archive-chunks/{$upload->id}";
        $disk->putFileAs($directory, $data['chunk'], (string) $data['index']);
        $received = array_values(array_unique([...($upload->received_chunks ?? []), (int) $data['index']]));
        sort($received);
        $upload->update(['received_chunks' => $received]);

        return response()->json(['received' => $received]);
    }

    public function completeChunkedUpload(Request $request, Booking $booking, ArchiveUploadSession $upload): RedirectResponse
    {
        abort_unless($upload->booking_id === $booking->id, 404);

        $received = $upload->received_chunks ?? [];
        if (count($received) !== $upload->total_chunks || $received !== range(0, $upload->total_chunks - 1)) {
            return back()->withErrors(['file' => 'لم تكتمل أجزاء الملف، حاول مرة أخرى.']);
        }

        $disk = Storage::disk('local');
        $directory = "archive-chunks/{$upload->id}";
        $assembled = $disk->path("archive-chunks/{$upload->id}-complete");
        $output = fopen($assembled, 'wb');
        for ($index = 0; $index < $upload->total_chunks; $index++) {
            $input = fopen($disk->path("{$directory}/{$index}"), 'rb');
            stream_copy_to_stream($input, $output);
            fclose($input);
        }
        fclose($output);

        $file = new UploadedFile($assembled, $upload->file_name, $upload->mime_type, null, true);
        Validator::make(['file' => $file], [
            'file' => ['required', 'file', 'max:'.self::MAX_FILE_KB, 'mimes:'.self::ALLOWED_MIMES],
        ], self::uploadMessages())->validate();

        $booking->addMedia($assembled)->usingFileName($upload->file_name)->toMediaCollection('archive-files');
        $disk->deleteDirectory($directory);
        $disk->delete("archive-chunks/{$upload->id}-complete");
        $upload->delete();

        return back()->with('success', 'تم رفع الملف بنجاح.');
    }

    public function destroyMedia(Media $media): RedirectResponse
    {
        $media->delete();

        return back()->with('success', 'تم حذف الملف.');
    }
}
