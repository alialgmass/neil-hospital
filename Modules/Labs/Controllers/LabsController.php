<?php

namespace Modules\Labs\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Labs\Actions\StoreLabResultAction;
use Modules\Labs\Http\Requests\StoreLabResultRequest;
use Modules\Labs\Services\LabsService;

class LabsController extends Controller
{
    public function __construct(
        private readonly LabsService $labsService,
        private readonly StoreLabResultAction $storeResultAction,
    ) {}

    public function index(): Response
    {
        $date = request('date', today()->toDateString());
        $search = request('search');

        return Inertia::render('labs/Index', [
            'queue' => $this->labsService->getQueue($date, $search),
            'date' => $date,
            'filters' => ['search' => $search],
        ]);
    }

    public function storeResult(StoreLabResultRequest $request, string $bookingId): RedirectResponse
    {
        $this->storeResultAction->execute($bookingId, $request->validated(), $request->user()->id);

        return back()->with('success', 'تم تسجيل نتيجة الفحص بنجاح.');
    }
}
