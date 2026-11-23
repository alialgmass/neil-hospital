<?php

namespace Modules\Booking\Services;

use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;

class MrnGeneratorService
{
    public function __construct(private readonly BookingRepositoryInterface $bookingRepository) {}

    /**
     * Generate the next file number in the format P-{seq}-{last3 of national ID}.
     * Example: P-100-457
     */
    public function generate(?string $nationalId = null): string
    {
        $last3 = $nationalId && strlen($nationalId) >= 3
            ? substr(preg_replace('/\D/', '', $nationalId), -3)
            : '000';

        $maxRetries = 5;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $seq = $this->bookingRepository->maxFileSequence() + 1 + $attempt;
            $fileNo = sprintf('P-%d-%s', $seq, $last3);

            if (! $this->bookingRepository->fileNoExists($fileNo)) {
                return $fileNo;
            }
        }

        // Fallback: use timestamp suffix
        return sprintf('P-%s-%s', substr((string) microtime(true), -6, 6), $last3);
    }
}
