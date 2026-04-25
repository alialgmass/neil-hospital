<?php

namespace Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $service
    ) {}

    public function index(): Response
    {
        $filters = request()->only(['module', 'user_id', 'from', 'to', 'search']);

        return Inertia::render('admin/ActivityLog', [
            'logs' => $this->service->list($filters, 50),
            'users' => $this->service->getUsers(),
            'modules' => $this->service->getModules(),
            'filters' => $filters,
        ]);
    }
}
