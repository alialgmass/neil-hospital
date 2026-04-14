<?php

namespace Modules\Booking\Services;

use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;

class MrnGeneratorService
{
    public function __construct(private readonly BookingRepositoryInterface $bookingRepository) {}

    /**
     * Generate the next MRN in the format MRN-YYYY-XXXXX.
     * Uses MAX(sequence) + UNIQUE constraint as a retry guard to prevent races.
     */
    public function generate(): string
    {
        $year = now()->year;
        $maxRetries = 5;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $seq = $this->bookingRepository->maxMrnSequence($year) + 1 + $attempt;
            $fileNo = sprintf('MRN-%d-%05d', $year, $seq);

            if (! $this->bookingRepository->fileNoExists($fileNo)) {
                return $fileNo;
            }
        }

        // Fallback: use timestamp-based suffix — extremely unlikely but safe
        return sprintf('MRN-%d-%s', $year, substr((string) microtime(true), -6, 6));
    }
}
