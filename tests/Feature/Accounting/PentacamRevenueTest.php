<?php

namespace Tests\Feature\Accounting;

use App\Enums\Department;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\AutoPostDoctorDuesAction;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AccountResolver;
use Tests\TestCase;

class PentacamRevenueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
    }

    public function test_pentacam_has_its_own_revenue_account(): void
    {
        $id = app(AccountResolver::class)->id(AccountCode::PENTACAM_REVENUE);

        $this->assertNotNull($id);
        $this->assertSame(AccountCode::PENTACAM_REVENUE, AccountCode::deptRevenueCode(Department::Pentacam));
    }

    public function test_pentacam_booking_does_not_accrue_doctor_dues(): void
    {
        app(AutoPostDoctorDuesAction::class)->execute(
            Department::Pentacam,
            500.0,
            'د. محمود الجارم',
            'MRN-PENTA-1',
        );

        $this->assertSame(0, JournalEntry::count());
    }

    public function test_non_pentacam_booking_still_accrues_doctor_dues(): void
    {
        app(AutoPostDoctorDuesAction::class)->execute(
            Department::Surgery,
            500.0,
            'د. محمود الجارم',
            'MRN-SURG-1',
        );

        $this->assertSame(1, JournalEntry::count());
    }
}
