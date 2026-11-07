<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Common ophthalmic diagnoses seeded so the catalog is usable on day one.
     * Doctors can still add new entries from the examination form.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const DEFAULT_DIAGNOSES = [
        ['Myopia', 'H52.1'],
        ['Hypermetropia', 'H52.0'],
        ['Astigmatism', 'H52.2'],
        ['Presbyopia', 'H52.4'],
        ['Age-related cataract', 'H25'],
        ['Congenital cataract', 'Q12.0'],
        ['Pseudophakia', 'Z96.1'],
        ['Posterior capsule opacification', 'H26.4'],
        ['Primary open-angle glaucoma', 'H40.1'],
        ['Angle-closure glaucoma', 'H40.2'],
        ['Ocular hypertension', 'H40.0'],
        ['Glaucoma suspect', 'H40.0'],
        ['Diabetic retinopathy', 'E11.3'],
        ['Diabetic macular edema', 'E11.3'],
        ['Hypertensive retinopathy', 'H35.0'],
        ['Age-related macular degeneration', 'H35.3'],
        ['Retinal detachment', 'H33'],
        ['Retinal vein occlusion', 'H34.8'],
        ['Central serous chorioretinopathy', 'H35.7'],
        ['Keratoconus', 'H18.6'],
        ['Dry eye syndrome', 'H04.1'],
        ['Allergic conjunctivitis', 'H10.1'],
        ['Bacterial conjunctivitis', 'H10.0'],
        ['Viral conjunctivitis', 'B30'],
        ['Bacterial keratitis', 'H16.0'],
        ['Corneal ulcer', 'H16.0'],
        ['Corneal abrasion', 'S05.0'],
        ['Pterygium', 'H11.0'],
        ['Pinguecula', 'H11.1'],
        ['Blepharitis', 'H01.0'],
        ['Chalazion', 'H00.1'],
        ['Hordeolum (stye)', 'H00.0'],
        ['Ptosis', 'H02.4'],
        ['Uveitis', 'H20'],
        ['Amblyopia', 'H53.0'],
        ['Strabismus', 'H50'],
        ['Optic neuritis', 'H46'],
        ['Vitreous hemorrhage', 'H43.1'],
        ['Posterior vitreous detachment', 'H43.8'],
        ['Nasolacrimal duct obstruction', 'H04.5'],
    ];

    public function up(): void
    {
        Schema::create('diagnoses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 150)->unique();
            $table->string('code', 20)->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('diagnoses')->insert(array_map(fn (array $row) => [
            'id' => (string) Str::ulid(),
            'name' => $row[0],
            'code' => $row[1],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::DEFAULT_DIAGNOSES));
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnoses');
    }
};
