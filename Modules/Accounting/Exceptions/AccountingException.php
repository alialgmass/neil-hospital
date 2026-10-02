<?php

namespace Modules\Accounting\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown whenever a caller attempts to post an invalid journal entry:
 * an unknown account code, a non-postable/summary account, an inactive
 * account, a zero/negative amount, debit == credit, or a posting the
 * accounting guide forbids (e.g. 5130 with no insurance revenue).
 *
 * In a web request it is shown to the user like a validation error
 * instead of a server error.
 */
class AccountingException extends RuntimeException
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'errors' => ['accounting' => [$this->getMessage()]]], 422);
        }

        return back()->withInput()->withErrors(['accounting' => $this->getMessage()]);
    }
}
