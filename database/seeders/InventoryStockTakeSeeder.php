<?php

namespace Database\Seeders;

use Database\Seeders\Historical\Concerns\NormalizesArabic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Actions\StockTakeAdjustmentAction;
use Modules\Inventory\Enums\ItemCategory;
use Modules\Inventory\Enums\ItemUnit;
use Modules\Inventory\Models\InventoryItem;

/**
 * Applies the physical stock count (جرد_المخزن.xlsx — من كشوف الجرد اليدوية) to the inventory.
 *
 * Run with:
 *   php artisan db:seed --class="Database\\Seeders\\InventoryStockTakeSeeder"
 *
 * - Every sheet row is matched to an existing item: lenses by type + power,
 *   supplies by Arabic-normalized name, or through SUPPLY_ALIASES for
 *   spelling variants. Rows that land on the same item are summed.
 * - A row with no match creates the item first (unit_cost 0, quantity 0).
 * - Counted quantity = store count × PACK_SIZES (when the sheet states the
 *   pack) + the numeric «جرد العمليات» (operating-room) count.
 * - Quantities are set through StockTakeAdjustmentAction: one ADJ permit,
 *   each variance posted at the item's unit_cost (surplus → 4220, shortage → 5010).
 * - Items absent from the sheets are untouched. Re-running is a no-op once
 *   the counts match.
 */
class InventoryStockTakeSeeder extends Seeder
{
    use NormalizesArabic;

    public const PERMIT_NOTES = 'جرد المخزن — كشوف الجرد اليدوية (جرد_المخزن.xlsx)';

    /**
     * Lens types as written in the sheet → the normalized key found in item names.
     * «لينة (ماينص)» is the minus soft lens, stored as «عدسه لينه باور -N».
     *
     * @var array<string, string>
     */
    private const LENS_TYPES = [
        'لينة (ماينص)' => 'لينه',
        'صلبة' => 'صلبه',
        'لينة' => 'لينه',
        'أمامية' => 'اماميه',
        'أمريكي' => 'امريكي',
        'أرتيزان' => 'ارتيزان',
        'إيرس كلو' => 'ايرس كلو',
    ];

    /** Sheet lens type whose powers are written without their minus sign. */
    private const MINUS_LENS_TYPE = 'لينة (ماينص)';

    /** Brand names that mark an existing item as an American (أمريكي) lens. */
    private const AMERICAN_LENS_BRANDS = ['انفستا', 'ايكون'];

    /**
     * Supply row number (م) → existing item name, where the sheet spells it differently.
     *
     * @var array<int, string>
     */
    private const SUPPLY_ALIASES = [
        3 => 'اوفر هيد مريض',
        4 => 'اوفر هيد طبيب',
        9 => 'كانيولا صفراء',
        10 => 'كانيولا بنفسجي',
        13 => 'كانيولا بينك',
        14 => 'كانيولا زرقاء',
        18 => 'بكر اوتريف',
        21 => 'اتركيام امبول',
        25 => 'كابرون امبول',
        26 => 'نالوفين امبول',
        27 => 'كيتولاك امبول',
        29 => 'جراميسين',
        32 => 'اى باتش',
        33 => 'اوبتبريد قطرة',
        35 => 'ديكسافلوكس قطرة',
        38 => 'رينجر لاكتات',
        39 => 'اوفر شوز مريض',
        40 => 'فوطه ثقيله',
        42 => 'انبوبه حنجريه 3',
        43 => 'انبوبه حنجريه 3.5',
        45 => 'انبوبه حنجريه 4',
        46 => 'انبوبه حنجريه 4.5',
        48 => 'انبوبه حنجريه 5',
        49 => 'انبوبه حنجريه 5.5',
        50 => 'انبوبه حنجريه 6',
        51 => 'انبوبه حنجريه 6.5',
        52 => 'انبوبه حنجريه 7 بكف',
        53 => 'انبوبه حنجريه 7.5',
        56 => 'كره عين',
        59 => 'قطره بليجيكا',
        60 => 'بينوكس امبولات',
        62 => 'سيكلوفرين قطرة',
        65 => 'فوطه ليزك',
        71 => 'قطره اوبتوزولين',
        75 => 'دايسنون امبول',
        76 => 'نيوستجمين امبول',
        77 => 'سيليكون 5000 (BIOSIL-F)',
        79 => 'سيليكون 1000 (BIOSIL-F)',
        85 => 'ابرليفان',
        86 => 'صبغة فلوروسين امبول',
        89 => 'فانكوميثين 1000',
        90 => 'شكاكه سكر',
        91 => 'شرائح السكر',
        95 => 'تنشن رينج',
        97 => 'سرنجه 1',
        98 => 'سرنجه3',
        99 => 'سرنجه 5',
        100 => 'سرنجه 5',
        102 => 'استرليم',
        103 => 'استرليم',
        106 => 'كحول',
        107 => 'ملمع زجاج',
        112 => 'كلور',
        115 => 'هاند ووش',
        116 => 'دفتر ايصالات تحصيل',
        117 => 'مناديل سحب',
        121 => 'بن ابو عوف للبوفيه',
        122 => 'شاي بوفيه',
        123 => 'سكر بوفيه',
        124 => 'دفتر التحويل',
        131 => 'كيراتوم',
        132 => 'اسبونج ليزك',
        136 => 'جوانتى 7 معقم',
        137 => 'جوانتى 8 معقم',
        138 => 'جوانتى 7.5 معقم',
        139 => 'علبه كمامه برباط',
        140 => 'علبه كمامه استك',
        141 => 'جوانتي لاتكس',
        143 => 'بروبوفور امبول',
    ];

    /**
     * Supply row number (م) → pieces per counted pack, only where the sheet states it
     * explicitly (e.g. «سرنجات 1 سم: 2 ×250») and the matched item is costed per piece.
     * Overheads (rows 3, 4) stay in packs — «اوفر هيد مريض» is costed per pack.
     *
     * @var array<int, int>
     */
    private const PACK_SIZES = [
        11 => 25, 20 => 24, 25 => 6, 27 => 5, 38 => 20, 56 => 5,
        76 => 5, 78 => 50, 92 => 100, 93 => 100, 97 => 250, 98 => 150, 99 => 200,
        100 => 200, 101 => 125, 132 => 18, 133 => 5, 134 => 10, 136 => 50, 137 => 50,
    ];

    /**
     * Lens stock — sheet «جرد العدسات», one row per lens type + power.
     *
     * @var list<array{type: string, power: string, quantity: int|float, expiry: ?string, expiry_text: ?string, notes: ?string, needs_review: bool, or_count: ?string}>
     */
    private const LENSES = [
        ['type' => 'صلبة', 'power' => '1', 'quantity' => 4, 'expiry' => '2027-06-01', 'expiry_text' => '2×10/2028 + 2×6/2027', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '2', 'quantity' => 3, 'expiry' => '2028-10-01', 'expiry_text' => '2×10/2028 + 1×6/2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '3', 'quantity' => 4, 'expiry' => '2028-10-01', 'expiry_text' => '3×10/2028 + 1×4/2030', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '4', 'quantity' => 1, 'expiry' => '2027-06-01', 'expiry_text' => '6/2027', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '5', 'quantity' => 4, 'expiry' => '2028-10-01', 'expiry_text' => '2×6/2029 + 2×10/2028', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '6', 'quantity' => 4, 'expiry' => '2027-09-01', 'expiry_text' => '1×10/2028 + 1×1/2030 + 1×12/27 1x9/27', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '7', 'quantity' => 3, 'expiry' => '2027-10-01', 'expiry_text' => '2×10/2027 + 1×6/2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '8', 'quantity' => 2, 'expiry' => '2027-03-01', 'expiry_text' => '1×10/2027 + 1×3/2027', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '9', 'quantity' => 3, 'expiry' => '2027-06-01', 'expiry_text' => '1×6/2027 + 2×5/2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '10', 'quantity' => 1, 'expiry' => '2029-06-01', 'expiry_text' => '6/2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '11', 'quantity' => 3, 'expiry' => '2028-10-01', 'expiry_text' => '10/2028', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '12', 'quantity' => 3, 'expiry' => '2028-10-01', 'expiry_text' => '3×10/2028', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '13', 'quantity' => 3, 'expiry' => '2027-06-01', 'expiry_text' => '2×6/2027 + 1×6/2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '14', 'quantity' => 3, 'expiry' => '2029-04-01', 'expiry_text' => '1×6/2029 + 1×4/2029 + 1×10/27', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '15', 'quantity' => 3, 'expiry' => '2027-09-01', 'expiry_text' => '1×4/2029 + 2×9/2027', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '16', 'quantity' => 2, 'expiry' => '2029-01-01', 'expiry_text' => '1×1/2029 + 1×7/2030', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '17', 'quantity' => 2, 'expiry' => '2029-04-01', 'expiry_text' => '1×4/2029 + 1×6/2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '18', 'quantity' => 3, 'expiry' => '2027-08-01', 'expiry_text' => '2×8/2027 + 1×12/2028', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '19', 'quantity' => 1, 'expiry' => '2029-05-01', 'expiry_text' => '5/2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '20', 'quantity' => 1, 'expiry' => '2029-05-01', 'expiry_text' => '5/2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '21', 'quantity' => 3, 'expiry' => '2030-04-01', 'expiry_text' => '1×3/2031 + 2×4/2030', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '22', 'quantity' => 2, 'expiry' => '2030-04-01', 'expiry_text' => '4/2030', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '23', 'quantity' => 2, 'expiry' => '2030-07-01', 'expiry_text' => '7/2030', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '24', 'quantity' => 2, 'expiry' => '2030-07-01', 'expiry_text' => '7/2030', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '25', 'quantity' => 3, 'expiry' => '2028-10-01', 'expiry_text' => '1×5/2029 + 2×10/2028', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '26', 'quantity' => 2, 'expiry' => '2029-05-01', 'expiry_text' => '1×4/2030 + 1×5/2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '27', 'quantity' => 2, 'expiry' => '2029-02-01', 'expiry_text' => '2/2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '28', 'quantity' => 2, 'expiry' => '2028-10-01', 'expiry_text' => '10/2028', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '29', 'quantity' => 2, 'expiry' => '2028-08-01', 'expiry_text' => '1×8/2028 + 1×6/2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'صلبة', 'power' => '30', 'quantity' => 2, 'expiry' => '2029-06-01', 'expiry_text' => '6/2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة (ماينص)', 'power' => '1', 'quantity' => 1, 'expiry' => '2029-04-01', 'expiry_text' => '4/2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'لينة (ماينص)', 'power' => '2', 'quantity' => 3, 'expiry' => '2028-01-01', 'expiry_text' => '2×1/2028 + 1×7/2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'لينة (ماينص)', 'power' => '3', 'quantity' => 1, 'expiry' => '2028-05-01', 'expiry_text' => '5/2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'لينة (ماينص)', 'power' => '4', 'quantity' => 4, 'expiry' => '2028-11-01', 'expiry_text' => '2×11/2028 + 1×8/2029 + 1×7/2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'لينة (ماينص)', 'power' => '5', 'quantity' => 2, 'expiry' => '2027-11-01', 'expiry_text' => '11/2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'لينة (ماينص)', 'power' => '6', 'quantity' => 2, 'expiry' => '2027-11-01', 'expiry_text' => '11/2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'لينة (ماينص)', 'power' => '7', 'quantity' => 4, 'expiry' => '2027-02-01', 'expiry_text' => '3×11/2027 + 1×2/2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'لينة (ماينص)', 'power' => '8', 'quantity' => 1, 'expiry' => '2027-11-01', 'expiry_text' => '11/2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'لينة (ماينص)', 'power' => '9', 'quantity' => 2, 'expiry' => '2027-04-01', 'expiry_text' => '1×11/2027 + 1×4/2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'لينة (ماينص)', 'power' => '10', 'quantity' => 1, 'expiry' => '2027-11-01', 'expiry_text' => '11/2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '1', 'quantity' => 1, 'expiry' => '2028-06-01', 'expiry_text' => '6/2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '2', 'quantity' => 1, 'expiry' => '2029-01-01', 'expiry_text' => '1/2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '3', 'quantity' => 1, 'expiry' => '2030-02-01', 'expiry_text' => '2/2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '4', 'quantity' => 1, 'expiry' => '2030-02-01', 'expiry_text' => '2/2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '5', 'quantity' => 1, 'expiry' => '2030-02-01', 'expiry_text' => '2/2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '6', 'quantity' => 1, 'expiry' => '2029-05-01', 'expiry_text' => '5/2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '7', 'quantity' => 0, 'expiry' => null, 'expiry_text' => 'لا يوجد', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '8', 'quantity' => 0, 'expiry' => null, 'expiry_text' => 'لا يوجد', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '9', 'quantity' => 0, 'expiry' => null, 'expiry_text' => 'لا يوجد', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '10', 'quantity' => 0, 'expiry' => null, 'expiry_text' => 'لا يوجد', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '11', 'quantity' => 1, 'expiry' => '2029-06-01', 'expiry_text' => '6/2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '12', 'quantity' => 1, 'expiry' => '2028-06-01', 'expiry_text' => '6/2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '13', 'quantity' => 0, 'expiry' => null, 'expiry_text' => 'لا يوجد', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '14', 'quantity' => 1, 'expiry' => '2029-06-01', 'expiry_text' => '6/2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '15', 'quantity' => 0, 'expiry' => null, 'expiry_text' => 'لا يوجد', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '16', 'quantity' => 1, 'expiry' => '2029-01-01', 'expiry_text' => '1/2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '17', 'quantity' => 1, 'expiry' => '2030-02-01', 'expiry_text' => '2/2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '18', 'quantity' => 1, 'expiry' => '2030-02-01', 'expiry_text' => '2/2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '19', 'quantity' => 0, 'expiry' => null, 'expiry_text' => 'لا يوجد', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '20', 'quantity' => 1, 'expiry' => '2030-02-01', 'expiry_text' => '2/2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '21', 'quantity' => 1, 'expiry' => '2029-06-01', 'expiry_text' => '6/2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '22', 'quantity' => 1, 'expiry' => '2029-06-01', 'expiry_text' => '6/2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '23', 'quantity' => 1, 'expiry' => '2028-10-01', 'expiry_text' => '10/2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '24', 'quantity' => 1, 'expiry' => '2030-02-01', 'expiry_text' => '2/2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمامية', 'power' => '25', 'quantity' => 1, 'expiry' => '2029-05-01', 'expiry_text' => '5/2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'لينة', 'power' => '0', 'quantity' => 5, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '1', 'quantity' => 8, 'expiry' => '2028-01-01', 'expiry_text' => 'P 2028 + A 2029', 'notes' => null, 'needs_review' => false, 'or_count' => '2'],
        ['type' => 'لينة', 'power' => '2', 'quantity' => 5, 'expiry' => '2027-01-01', 'expiry_text' => 'P 2027 + A 2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '3', 'quantity' => 6, 'expiry' => '2027-01-01', 'expiry_text' => 'P 2027 + 2028 + 2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '4', 'quantity' => 3, 'expiry' => '2028-01-01', 'expiry_text' => 'P 2028 + 2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '5', 'quantity' => 5, 'expiry' => '2028-01-01', 'expiry_text' => 'P 2028', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '6', 'quantity' => 3, 'expiry' => '2028-01-01', 'expiry_text' => 'P 2028', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '7', 'quantity' => 2, 'expiry' => '2027-01-01', 'expiry_text' => 'P 2027 + 2028', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '8', 'quantity' => 3, 'expiry' => '2027-01-01', 'expiry_text' => 'P 2027(1) + 2029(1) + Ocuflex 2027(1)', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '9', 'quantity' => 3, 'expiry' => '2029-01-01', 'expiry_text' => 'P 2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '10', 'quantity' => 3, 'expiry' => '2027-01-01', 'expiry_text' => 'A 2029 + Ocuflex 2027(1)', 'notes' => null, 'needs_review' => false, 'or_count' => '0'],
        ['type' => 'لينة', 'power' => '11', 'quantity' => 5, 'expiry' => '2028-01-01', 'expiry_text' => 'P 2028 + 2029', 'notes' => null, 'needs_review' => false, 'or_count' => '2'],
        ['type' => 'لينة', 'power' => '12', 'quantity' => 2, 'expiry' => '2028-01-01', 'expiry_text' => 'P 2028', 'notes' => null, 'needs_review' => false, 'or_count' => '2'],
        ['type' => 'لينة', 'power' => '13', 'quantity' => 7, 'expiry' => '2027-01-01', 'expiry_text' => 'P 2029 + Ocuflex 2027(2)', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '14', 'quantity' => 0, 'expiry' => null, 'expiry_text' => 'مفيش', 'notes' => null, 'needs_review' => false, 'or_count' => '0'],
        ['type' => 'لينة', 'power' => '15', 'quantity' => 6, 'expiry' => '2028-01-01', 'expiry_text' => '2 P 2028 + Aquale 4/2030', 'notes' => null, 'needs_review' => false, 'or_count' => '2'],
        ['type' => 'لينة', 'power' => '16', 'quantity' => 7, 'expiry' => '2029-01-01', 'expiry_text' => '4 x 2029 + 2030', 'notes' => null, 'needs_review' => false, 'or_count' => '2'],
        ['type' => 'لينة', 'power' => '17', 'quantity' => 5, 'expiry' => '2028-01-01', 'expiry_text' => 'A 2029 + Ocuflex 2028', 'notes' => null, 'needs_review' => false, 'or_count' => '5'],
        ['type' => 'لينة', 'power' => '18', 'quantity' => 17, 'expiry' => '2028-01-01', 'expiry_text' => 'A 2028', 'notes' => null, 'needs_review' => false, 'or_count' => '5'],
        ['type' => 'لينة', 'power' => '18.5', 'quantity' => 0, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '19', 'quantity' => 26, 'expiry' => '2028-01-01', 'expiry_text' => '10 x 2028 + A 2029', 'notes' => null, 'needs_review' => false, 'or_count' => '2'],
        ['type' => 'لينة', 'power' => '20', 'quantity' => 15, 'expiry' => '2029-01-01', 'expiry_text' => 'A 2029', 'notes' => null, 'needs_review' => false, 'or_count' => '7'],
        ['type' => 'لينة', 'power' => '20.5', 'quantity' => 0, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => '4'],
        ['type' => 'لينة', 'power' => '21', 'quantity' => 21, 'expiry' => '2029-01-01', 'expiry_text' => 'A 2029', 'notes' => null, 'needs_review' => false, 'or_count' => '4'],
        ['type' => 'لينة', 'power' => '22', 'quantity' => 2, 'expiry' => '2029-01-01', 'expiry_text' => 'A 2029', 'notes' => null, 'needs_review' => false, 'or_count' => '0'],
        ['type' => 'لينة', 'power' => '23', 'quantity' => 9, 'expiry' => '2027-01-01', 'expiry_text' => 'P 2027(1) + 2029(8)', 'notes' => null, 'needs_review' => false, 'or_count' => '0'],
        ['type' => 'لينة', 'power' => '24', 'quantity' => 4, 'expiry' => '2028-01-01', 'expiry_text' => 'P 2029(2) + Ocuflex 2028', 'notes' => null, 'needs_review' => false, 'or_count' => '6'],
        ['type' => 'لينة', 'power' => '25', 'quantity' => 24, 'expiry' => '2029-01-01', 'expiry_text' => '20 x 2029 + 4 x P 2029', 'notes' => null, 'needs_review' => false, 'or_count' => '4'],
        ['type' => 'لينة', 'power' => '26', 'quantity' => 7, 'expiry' => '2028-01-01', 'expiry_text' => 'A 2028 + 2029', 'notes' => null, 'needs_review' => false, 'or_count' => '2'],
        ['type' => 'لينة', 'power' => '27', 'quantity' => 4, 'expiry' => '2027-01-01', 'expiry_text' => '2x P 4/27 +2 x A 2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '28', 'quantity' => 1, 'expiry' => '2029-01-01', 'expiry_text' => 'A 2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '29', 'quantity' => 1, 'expiry' => '2029-01-01', 'expiry_text' => 'A 2029', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '30', 'quantity' => 2, 'expiry' => '2028-01-01', 'expiry_text' => 'A 2028', 'notes' => null, 'needs_review' => false, 'or_count' => '0'],
        ['type' => 'لينة', 'power' => '31', 'quantity' => 0, 'expiry' => null, 'expiry_text' => 'مفيش', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '32', 'quantity' => 1, 'expiry' => '2028-01-01', 'expiry_text' => 'A 2028', 'notes' => null, 'needs_review' => false, 'or_count' => '0'],
        ['type' => 'لينة', 'power' => '33', 'quantity' => 3, 'expiry' => '2028-01-01', 'expiry_text' => 'A 2028', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '34', 'quantity' => 4, 'expiry' => '2028-01-01', 'expiry_text' => 'A 2028 + P 2028', 'notes' => null, 'needs_review' => false, 'or_count' => '2'],
        ['type' => 'لينة', 'power' => '35', 'quantity' => 2, 'expiry' => '2027-11-01', 'expiry_text' => 'P 11/2027', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '36', 'quantity' => 3, 'expiry' => '2027-11-01', 'expiry_text' => 'P 11/2027 + OC 2028', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '37', 'quantity' => 0, 'expiry' => null, 'expiry_text' => 'مفيش', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '38', 'quantity' => 5, 'expiry' => '2027-01-01', 'expiry_text' => 'P 2027 + OC 2028', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '39', 'quantity' => 2, 'expiry' => '2028-01-01', 'expiry_text' => 'OC 2028', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'لينة', 'power' => '40', 'quantity' => 0, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['type' => 'أمريكي', 'power' => '1', 'quantity' => 2, 'expiry' => '2027-01-01', 'expiry_text' => '2/27', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '2', 'quantity' => 3, 'expiry' => '2027-01-01', 'expiry_text' => '2×2027 + 1×2028 موف', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '3', 'quantity' => 3, 'expiry' => '2027-01-01', 'expiry_text' => '2 x 2027 + 1 x 2028 موف', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '4', 'quantity' => 3, 'expiry' => '2027-01-01', 'expiry_text' => '2×2027 + 1×2030 موف', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '5', 'quantity' => 2, 'expiry' => '2027-01-01', 'expiry_text' => '2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '6', 'quantity' => 0, 'expiry' => null, 'expiry_text' => 'مفيش', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '7', 'quantity' => 3, 'expiry' => '2027-01-01', 'expiry_text' => '2×2027 + 1×2029 موف', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '8', 'quantity' => 3, 'expiry' => '2027-01-01', 'expiry_text' => '2×2027 + 1×2029 موف', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '9', 'quantity' => 4, 'expiry' => '2027-01-01', 'expiry_text' => '2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '10', 'quantity' => 5, 'expiry' => '2027-01-01', 'expiry_text' => '4×2027 + 1×2030 موف', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '11', 'quantity' => 5, 'expiry' => '2027-01-01', 'expiry_text' => '4×2027 + 1×2029 موف', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '12', 'quantity' => 4, 'expiry' => '2027-01-01', 'expiry_text' => '2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '13', 'quantity' => 4, 'expiry' => '2027-01-01', 'expiry_text' => '2×2027 + 1×2029 + 1 x 2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '14', 'quantity' => 3, 'expiry' => '2027-01-01', 'expiry_text' => '2×2027 + 1×2028 موف', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '15', 'quantity' => 3, 'expiry' => '2028-01-01', 'expiry_text' => '2×2028 + 1×2030 موف', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '16', 'quantity' => 4, 'expiry' => '2028-01-01', 'expiry_text' => '2×2028 + 1×2028 1 x 2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '17', 'quantity' => 3, 'expiry' => '2027-01-01', 'expiry_text' => '2×2029 + 1×2027 موف', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '18', 'quantity' => 6, 'expiry' => '2029-01-01', 'expiry_text' => '3×2029 + 2×2029 + 1x2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '19', 'quantity' => 4, 'expiry' => '2027-01-01', 'expiry_text' => '2027/2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '20', 'quantity' => 3, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '21', 'quantity' => 2, 'expiry' => '2029-01-01', 'expiry_text' => '1×2029 + 1×2030 موف', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '22', 'quantity' => 5, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '23', 'quantity' => 4, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '24', 'quantity' => 2, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '25', 'quantity' => 2, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '26', 'quantity' => 2, 'expiry' => '2027-12-01', 'expiry_text' => '12/2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '27', 'quantity' => 2, 'expiry' => '2027-12-01', 'expiry_text' => '12/2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '28', 'quantity' => 2, 'expiry' => '2027-01-01', 'expiry_text' => '1/2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '29', 'quantity' => 2, 'expiry' => '2027-05-01', 'expiry_text' => '5/2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أمريكي', 'power' => '30', 'quantity' => 2, 'expiry' => '2027-09-01', 'expiry_text' => '9/2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '1', 'quantity' => 2, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '2', 'quantity' => 1, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '3', 'quantity' => 0, 'expiry' => null, 'expiry_text' => 'مفيش', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '4', 'quantity' => 2, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '5', 'quantity' => 0, 'expiry' => null, 'expiry_text' => 'مفيش', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '6', 'quantity' => 2, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '7', 'quantity' => 4, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '8', 'quantity' => 5, 'expiry' => '2029-01-01', 'expiry_text' => '4x2029 + 1x2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '9', 'quantity' => 2, 'expiry' => '2030-01-01', 'expiry_text' => '2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '10', 'quantity' => 0, 'expiry' => null, 'expiry_text' => 'مفيش', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '11', 'quantity' => 1, 'expiry' => '2031-01-01', 'expiry_text' => '2031', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '12', 'quantity' => 3, 'expiry' => '2030-01-01', 'expiry_text' => '2x2030 + 1x2031', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '13', 'quantity' => 2, 'expiry' => '2030-01-01', 'expiry_text' => '2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '14', 'quantity' => 5, 'expiry' => '2028-01-01', 'expiry_text' => '4x2030 + 1x2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '15', 'quantity' => 2, 'expiry' => '2030-01-01', 'expiry_text' => '2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '16', 'quantity' => 3, 'expiry' => '2029-01-01', 'expiry_text' => '1x2029 + 2x2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '17', 'quantity' => 4, 'expiry' => '2030-01-01', 'expiry_text' => '2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '18', 'quantity' => 3, 'expiry' => '2029-01-01', 'expiry_text' => '1x2029 + 2x2031', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '19', 'quantity' => 6, 'expiry' => '2028-01-01', 'expiry_text' => '2x2028 + 2x2029 + 2x2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'أرتيزان', 'power' => '20', 'quantity' => 4, 'expiry' => '2029-01-01', 'expiry_text' => '2x2029 + 2x2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '1', 'quantity' => 4, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '2', 'quantity' => 4, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '3', 'quantity' => 3, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '4', 'quantity' => 4, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '5', 'quantity' => 4, 'expiry' => '2027-10-01', 'expiry_text' => '10/2027(1) + 2028(1) + 2029(1) + 2030(1)', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '6', 'quantity' => 3, 'expiry' => '2029-01-01', 'expiry_text' => '2029(1) + 2030(2)', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '7', 'quantity' => 2, 'expiry' => '2030-01-01', 'expiry_text' => '2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '8', 'quantity' => 3, 'expiry' => '2029-01-01', 'expiry_text' => '2029(2) + 2030(1)', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '9', 'quantity' => 2, 'expiry' => '2030-01-01', 'expiry_text' => '2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '10', 'quantity' => 9, 'expiry' => '2029-01-01', 'expiry_text' => '8x2030 + 1x2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '11', 'quantity' => 3, 'expiry' => '2029-01-01', 'expiry_text' => '2030(2) + 2029(1)', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '12', 'quantity' => 3, 'expiry' => '2029-01-01', 'expiry_text' => '2030(2) + 2029(1)', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '13', 'quantity' => 2, 'expiry' => '2028-01-01', 'expiry_text' => '2030(1) + 2028(1)', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '14', 'quantity' => 5, 'expiry' => '2030-01-01', 'expiry_text' => '2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '15', 'quantity' => 2, 'expiry' => '2030-01-01', 'expiry_text' => '2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '16', 'quantity' => 2, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '17', 'quantity' => 2, 'expiry' => '2030-01-01', 'expiry_text' => '2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '18', 'quantity' => 3, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '19', 'quantity' => 7, 'expiry' => '2030-01-01', 'expiry_text' => '2030(5) + 2031(2)', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '20', 'quantity' => 7, 'expiry' => '2030-01-01', 'expiry_text' => '2030(5) + 2031(2)', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '21', 'quantity' => 3, 'expiry' => '2030-01-01', 'expiry_text' => '2030(2) + 2031(1)', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '22', 'quantity' => 5, 'expiry' => '2030-01-01', 'expiry_text' => '2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '23', 'quantity' => 5, 'expiry' => '2030-01-01', 'expiry_text' => '2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '24', 'quantity' => 3, 'expiry' => '2029-01-01', 'expiry_text' => '2030 + 2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '25', 'quantity' => 4, 'expiry' => '2029-01-01', 'expiry_text' => '2029 + 2030(1)', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '26', 'quantity' => 2, 'expiry' => '2031-01-01', 'expiry_text' => '2031', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '27', 'quantity' => 1, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '28', 'quantity' => 2, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '29', 'quantity' => 4, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['type' => 'إيرس كلو', 'power' => '30', 'quantity' => 3, 'expiry' => '2029-01-01', 'expiry_text' => '1x2029 + 2x2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
    ];

    /**
     * Supplies & drugs — sheet «المستلزمات والأدوية».
     *
     * @var list<array{no: int, name: string, category: ItemCategory, unit: ItemUnit, quantity: int|float, pack: ?string, expiry: ?string, expiry_text: ?string, notes: ?string, needs_review: bool, or_count: ?string}>
     */
    private const SUPPLIES = [
        ['no' => 1, 'name' => 'قسطرة أورانج', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 14, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 2, 'name' => 'قسطرة أخضر', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 10, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 3, 'name' => 'أوفرهيد مريض', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 40, 'pack' => '×100', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 4, 'name' => 'أوفرهيد طبيب', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Pack, 'quantity' => 26, 'pack' => 'كيس ×50', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 5, 'name' => 'لفات بلاستير', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 10, 'pack' => 'علب', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 6, 'name' => 'ماركر', 'category' => ItemCategory::Office, 'unit' => ItemUnit::Box, 'quantity' => 1, 'pack' => 'علبة', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 7, 'name' => 'اسورة مريض', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 2, 'pack' => 'علبة', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 8, 'name' => 'صواب كحول', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 1, 'pack' => 'علبة', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => true, 'or_count' => null],
        ['no' => 9, 'name' => 'كانيولا أصفر', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 260, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 10, 'name' => 'كانيولا موف', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 149, 'pack' => null, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 11, 'name' => 'جهاز محاليل', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Pack, 'quantity' => 13, 'pack' => 'باكت ×25', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 12, 'name' => 'ايزو غسيل الالات', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Pack, 'quantity' => 40, 'pack' => 'باكت', 'expiry' => null, 'expiry_text' => null, 'notes' => 'اسم الصنف مش واضح', 'needs_review' => true, 'or_count' => null],
        ['no' => 13, 'name' => 'كانيولا بمبي', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 85, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 14, 'name' => 'كانيولا أزرق', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 100, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 15, 'name' => 'جهاز محاليل', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 5, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 16, 'name' => 'جون مريض', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 70, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 17, 'name' => 'بكر سونار', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 9, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 18, 'name' => 'بكر اوتوريف', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 132, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => 'اسم الصنف مش واضح', 'needs_review' => true, 'or_count' => null],
        ['no' => 19, 'name' => 'حقن افالنستين', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 36, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => 'اسم الصنف مش واضح', 'needs_review' => true, 'or_count' => null],
        ['no' => 20, 'name' => 'أفيل', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 4, 'pack' => 'علبة ×24', 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 21, 'name' => 'أتراكوريوم أمبول', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Ampoule, 'quantity' => 10, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 22, 'name' => 'هيدروكورتيزون مرهم', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 8, 'pack' => 'علبة', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 23, 'name' => 'هيلون', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 15, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 24, 'name' => 'هيدروكورتيزون حقن', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 5, 'pack' => 'علبة', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 25, 'name' => 'كابرون', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 3, 'pack' => 'علب ×6', 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 26, 'name' => 'نالوفون', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 0, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 27, 'name' => 'كيتولاك', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 4, 'pack' => 'علب ×5', 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 28, 'name' => 'ديكساميثازون أمبول', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Ampoule, 'quantity' => 105, 'pack' => '(105 ×3)', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 29, 'name' => 'جاراميسين', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 40, 'pack' => null, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 30, 'name' => 'شاش عمليات', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Pack, 'quantity' => 30, 'pack' => '2 باكت', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 31, 'name' => 'ديكساميثازون', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 4, 'pack' => 'x 5علب x 5', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 32, 'name' => 'علبة آي باتش', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 32, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => '4'],
        ['no' => 33, 'name' => 'أوبتيبرو قطرة', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 40, 'pack' => null, 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 34, 'name' => 'بريدفورت', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 24, 'pack' => null, 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => 'اسم الصنف مش واضح', 'needs_review' => true, 'or_count' => null],
        ['no' => 35, 'name' => 'ديكسافلوكس (؟)', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 112, 'pack' => null, 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => null, 'needs_review' => true, 'or_count' => null],
        ['no' => 36, 'name' => 'نيفكسال', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 49, 'pack' => null, 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => 'اسم الصنف مش واضح', 'needs_review' => true, 'or_count' => null],
        ['no' => 37, 'name' => 'سالكودين', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 167, 'pack' => null, 'expiry' => '2027-01-01', 'expiry_text' => '2027', 'notes' => 'اسم الصنف مش واضح', 'needs_review' => true, 'or_count' => null],
        ['no' => 38, 'name' => 'رينجر', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Bottle, 'quantity' => 10, 'pack' => 'كراتين ×20 زجاجة', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => '8'],
        ['no' => 39, 'name' => 'أوفرشوز', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 33, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 40, 'name' => 'فوطة تقيلة', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 60, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => '17'],
        ['no' => 41, 'name' => 'فوطة خفيفة', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 45, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => '28'],
        ['no' => 42, 'name' => 'أنبوبة حنجرية مقاس 3', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 31, 'pack' => 'انبوبه', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 43, 'name' => 'أنبوبة حنجرية مقاس 3.5', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 15, 'pack' => 'انبوبه', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 44, 'name' => 'أنبوبة حنجرية مقاس 3.5 بكفة', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 43, 'pack' => 'انبوبه', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 45, 'name' => 'أنبوبة حنجرية مقاس 4', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 10, 'pack' => 'انبوبه', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 46, 'name' => 'أنبوبة حنجرية مقاس 4.5 بكفة', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 9, 'pack' => 'انبوبه', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 47, 'name' => 'أنبوبة حنجرية مقاس 8', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 8, 'pack' => 'انبوبه', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 48, 'name' => 'أنبوبة حنجرية مقاس 5 بكفة', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 19, 'pack' => 'انبوبه', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 49, 'name' => 'أنبوبة حنجرية مقاس 5.5 بكفة', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 23, 'pack' => 'انبوبه', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 50, 'name' => 'أنبوبة حنجرية مقاس 6 بكفة', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 12, 'pack' => 'انبوبه', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 51, 'name' => 'أنبوبة حنجرية مقاس 6.5 بكفة', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 13, 'pack' => 'انبوبه', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 52, 'name' => 'أنبوبة حنجرية مقاس 7 بكفة', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 8, 'pack' => 'انبوبه', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 53, 'name' => 'أنبوبة حنجرية مقاس 7.5 بكفة', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 8, 'pack' => 'انبوبه', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 54, 'name' => 'أنبوبة دمعية', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 25, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 55, 'name' => 'غطيان باكت', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Pack, 'quantity' => 10, 'pack' => 'باكت', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 56, 'name' => 'كورة عين (مقاسات 16 / 12 / 14)', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Pack, 'quantity' => 3, 'pack' => 'باكت — كل باكت فيه 5', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 57, 'name' => 'ماركين أمبول', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Ampoule, 'quantity' => 90, 'pack' => null, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 58, 'name' => 'أوكسيبوكارنين', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 16, 'pack' => null, 'expiry' => '2027-01-01', 'expiry_text' => '2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 59, 'name' => 'بليجيكا', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 5, 'pack' => null, 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => null, 'needs_review' => true, 'or_count' => null],
        ['no' => 60, 'name' => 'بينوكس', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 52, 'pack' => null, 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => null, 'needs_review' => true, 'or_count' => null],
        ['no' => 61, 'name' => 'جون طبيب', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 43, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => '5'],
        ['no' => 62, 'name' => 'سيكلوفرين', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 76, 'pack' => null, 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => null, 'needs_review' => false, 'or_count' => '1'],
        ['no' => 63, 'name' => 'هاي فريش', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 151, 'pack' => null, 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 64, 'name' => 'أوفرهيد', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 261, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 65, 'name' => 'فوط ليزك', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 194, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 66, 'name' => '(صنف غير مقروء) — بند 66', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 50, 'pack' => null, 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => null, 'needs_review' => true, 'or_count' => null],
        ['no' => 67, 'name' => '(صنف غير مقروء) — بند 67', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 1, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => true, 'or_count' => null],
        ['no' => 68, 'name' => 'ديكساميثازون حقن', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 3, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 69, 'name' => 'Milomycin حقن', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 8, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 70, 'name' => '(صنف غير مقروء) — بند 70', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 7, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => true, 'or_count' => null],
        ['no' => 71, 'name' => 'اوبتيزولين', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 1, 'pack' => null, 'expiry' => '2027-01-01', 'expiry_text' => '2027', 'notes' => null, 'needs_review' => true, 'or_count' => null],
        ['no' => 72, 'name' => 'فورتام', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 27, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 73, 'name' => 'أدرينالين أمبول', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Ampoule, 'quantity' => 108, 'pack' => null, 'expiry' => '2027-01-01', 'expiry_text' => '2027', 'notes' => null, 'needs_review' => false, 'or_count' => '6'],
        ['no' => 74, 'name' => 'أتروبين أمبول', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Ampoule, 'quantity' => 28, 'pack' => null, 'expiry' => '2027-01-01', 'expiry_text' => '2027', 'notes' => null, 'needs_review' => false, 'or_count' => '8'],
        ['no' => 75, 'name' => 'دايسينون', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 10, 'pack' => null, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => 'اسم الصنف مش واضح', 'needs_review' => true, 'or_count' => null],
        ['no' => 76, 'name' => 'نيوستجمن', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 4, 'pack' => 'علب ×5', 'expiry' => '2027-01-01', 'expiry_text' => '2027', 'notes' => null, 'needs_review' => true, 'or_count' => null],
        ['no' => 77, 'name' => 'سيلكون 5000', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 7, 'pack' => 'علب', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => '5'],
        ['no' => 78, 'name' => 'هايلون', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 5, 'pack' => 'علب ×50', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => true, 'or_count' => null],
        ['no' => 79, 'name' => 'سيلكون 1000', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 4, 'pack' => 'علب ×1000', 'expiry' => null, 'expiry_text' => null, 'notes' => 'محتاجين', 'needs_review' => false, 'or_count' => '4'],
        ['no' => 80, 'name' => 'صبغة شبكية', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 11, 'pack' => 'علبة', 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 81, 'name' => 'إيزون', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Bottle, 'quantity' => 7, 'pack' => 'زجاحة', 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => 'اسم الصنف مش واضح', 'needs_review' => true, 'or_count' => null],
        ['no' => 82, 'name' => 'صبغة فاكو', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 0, 'pack' => null, 'expiry' => null, 'expiry_text' => 'مفيش', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 83, 'name' => 'أوكريبن', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 0, 'pack' => null, 'expiry' => null, 'expiry_text' => 'مفيش', 'notes' => null, 'needs_review' => true, 'or_count' => null],
        ['no' => 84, 'name' => 'ديكالين', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 7, 'pack' => null, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => '2'],
        ['no' => 85, 'name' => 'ابرليفان أمبول', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Ampoule, 'quantity' => 66, 'pack' => null, 'expiry' => '2029-01-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => '5'],
        ['no' => 86, 'name' => 'صبغة اشاعات أمبول', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Ampoule, 'quantity' => 70, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 87, 'name' => 'صبغة (؟)', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Pack, 'quantity' => 93, 'pack' => 'باكت', 'expiry' => null, 'expiry_text' => null, 'notes' => 'اسم الصنف مش واضح', 'needs_review' => true, 'or_count' => null],
        ['no' => 88, 'name' => 'Gadicin (؟)', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 5, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => true, 'or_count' => null],
        ['no' => 89, 'name' => 'Vancomycin', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 1, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 90, 'name' => 'علبة شكاكة سكر', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 1, 'pack' => 'علبة', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 91, 'name' => 'شرائح سكر', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 0, 'pack' => null, 'expiry' => null, 'expiry_text' => 'مفيش', 'notes' => null, 'needs_review' => true, 'or_count' => '1'],
        ['no' => 92, 'name' => 'مشرط 11', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 4, 'pack' => '×100', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 93, 'name' => 'مشرط 15', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 2, 'pack' => '×100', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 94, 'name' => 'انبوبة مرهم عين', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 25, 'pack' => 'أنبوبة', 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => null, 'needs_review' => true, 'or_count' => null],
        ['no' => 95, 'name' => 'نيشن رينج', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 3, 'pack' => null, 'expiry' => '2027-01-01', 'expiry_text' => '2027', 'notes' => null, 'needs_review' => true, 'or_count' => null],
        ['no' => 96, 'name' => 'زايكولين', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 155, 'pack' => null, 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 97, 'name' => 'سرنجات 1 سم', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 2, 'pack' => '×250', 'expiry' => '2026-01-01', 'expiry_text' => '2026', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 98, 'name' => 'سرنجات 3 سم', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 4, 'pack' => '×150', 'expiry' => '2030-12-01', 'expiry_text' => '12/2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 99, 'name' => 'سرنجات 5 سم', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 7, 'pack' => '×200', 'expiry' => '2029-01-01', 'expiry_text' => '1/2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 100, 'name' => 'سرنجات 5 سم', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 3, 'pack' => '×200', 'expiry' => '2026-03-01', 'expiry_text' => '3/2026', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 101, 'name' => 'سرنجات 10 سم', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 4, 'pack' => '×125', 'expiry' => '2030-09-01', 'expiry_text' => '9/2030', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 102, 'name' => 'استرليم قديم', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 17, 'pack' => null, 'expiry' => '2026-01-01', 'expiry_text' => '2026', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 103, 'name' => 'استرليم جديد', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 18, 'pack' => null, 'expiry' => '2029-07-01', 'expiry_text' => '7/2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 104, 'name' => 'نفتالين حبوب', 'category' => ItemCategory::Cleaning, 'unit' => ItemUnit::Piece, 'quantity' => 17, 'pack' => null, 'expiry' => '2027-06-01', 'expiry_text' => '6/2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 105, 'name' => 'نفتالين كبير', 'category' => ItemCategory::Cleaning, 'unit' => ItemUnit::Piece, 'quantity' => 2, 'pack' => null, 'expiry' => '2028-05-01', 'expiry_text' => '5/2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 106, 'name' => 'كحول خام', 'category' => ItemCategory::Cleaning, 'unit' => ItemUnit::Piece, 'quantity' => 5, 'pack' => null, 'expiry' => '2031-09-01', 'expiry_text' => '9/2031', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 107, 'name' => 'منظف زجاج', 'category' => ItemCategory::Cleaning, 'unit' => ItemUnit::Piece, 'quantity' => 1, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 108, 'name' => 'منظف خشب', 'category' => ItemCategory::Cleaning, 'unit' => ItemUnit::Piece, 'quantity' => 3, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 109, 'name' => 'ملمع خشب', 'category' => ItemCategory::Cleaning, 'unit' => ItemUnit::Piece, 'quantity' => 6, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 110, 'name' => 'معطر جو', 'category' => ItemCategory::Cleaning, 'unit' => ItemUnit::Piece, 'quantity' => 4, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 111, 'name' => 'منظف أرضيات', 'category' => ItemCategory::Cleaning, 'unit' => ItemUnit::Piece, 'quantity' => 2, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 112, 'name' => 'كلوركس', 'category' => ItemCategory::Cleaning, 'unit' => ItemUnit::Piece, 'quantity' => 3, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 113, 'name' => 'مبيد', 'category' => ItemCategory::Cleaning, 'unit' => ItemUnit::Piece, 'quantity' => 2, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 114, 'name' => 'مسحوق غسيل', 'category' => ItemCategory::Cleaning, 'unit' => ItemUnit::Piece, 'quantity' => 1, 'pack' => 'كيلوجرام', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 115, 'name' => 'هاند واش', 'category' => ItemCategory::Cleaning, 'unit' => ItemUnit::Piece, 'quantity' => 4, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 116, 'name' => 'إيصال تحصيل', 'category' => ItemCategory::Office, 'unit' => ItemUnit::Piece, 'quantity' => 15, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 117, 'name' => 'مناديل', 'category' => ItemCategory::Cleaning, 'unit' => ItemUnit::Pack, 'quantity' => 4, 'pack' => 'باكت', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 118, 'name' => 'بيتادين أحمر', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 4, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 119, 'name' => 'مزيل صداء', 'category' => ItemCategory::Cleaning, 'unit' => ItemUnit::Piece, 'quantity' => 1, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 120, 'name' => 'جيل سونار', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 1, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 121, 'name' => 'بن', 'category' => ItemCategory::Other, 'unit' => ItemUnit::Piece, 'quantity' => 3, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 122, 'name' => 'شاي', 'category' => ItemCategory::Other, 'unit' => ItemUnit::Piece, 'quantity' => 2, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 123, 'name' => 'سكر', 'category' => ItemCategory::Other, 'unit' => ItemUnit::Piece, 'quantity' => 2, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 124, 'name' => 'دفتر تحويل', 'category' => ItemCategory::Office, 'unit' => ItemUnit::Pack, 'quantity' => 8, 'pack' => 'باكت', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 125, 'name' => 'ميثيل', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 110, 'pack' => null, 'expiry' => '2027-07-01', 'expiry_text' => '7/2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 126, 'name' => 'خيط 10/0', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 2, 'pack' => null, 'expiry' => '2031-07-01', 'expiry_text' => '07/2031', 'notes' => null, 'needs_review' => false, 'or_count' => '2 علبة'],
        ['no' => 127, 'name' => 'خيط 7/0', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 2, 'pack' => null, 'expiry' => '2031-07-01', 'expiry_text' => '7/2021', 'notes' => null, 'needs_review' => false, 'or_count' => '2 علبة'],
        ['no' => 128, 'name' => 'خيط 5/0', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 1, 'pack' => null, 'expiry' => '2030-11-01', 'expiry_text' => '11/2030', 'notes' => null, 'needs_review' => false, 'or_count' => '2 علبة'],
        ['no' => 129, 'name' => 'خيط 6/0', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 3, 'pack' => null, 'expiry' => '2031-07-01', 'expiry_text' => '07/2031', 'notes' => null, 'needs_review' => false, 'or_count' => '2 علبة'],
        ['no' => 130, 'name' => 'MVR', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 15, 'pack' => null, 'expiry' => '2031-07-01', 'expiry_text' => '07/2031', 'notes' => null, 'needs_review' => false, 'or_count' => 'علبة 3 وحدة'],
        ['no' => 131, 'name' => 'كريتوم', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 18, 'pack' => null, 'expiry' => '2031-07-01', 'expiry_text' => '07/2031', 'notes' => null, 'needs_review' => false, 'or_count' => 'علبة 3 وحدة'],
        ['no' => 132, 'name' => 'إسفنج ليزك', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 7, 'pack' => 'علب ×18', 'expiry' => '2031-07-01', 'expiry_text' => '07/2031', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 133, 'name' => 'كريسنت', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 4, 'pack' => 'علب ×5', 'expiry' => '2029-07-01', 'expiry_text' => '7/2029', 'notes' => null, 'needs_review' => false, 'or_count' => 'وحدة 4'],
        ['no' => 134, 'name' => 'مشرط ليزك', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 8, 'pack' => 'علب ×10', 'expiry' => '2029-06-01', 'expiry_text' => '2029', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 135, 'name' => 'شاشات ليزك', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 6, 'pack' => 'علبة', 'expiry' => '2027-11-01', 'expiry_text' => '11/2027', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 136, 'name' => 'جوانتي مقاس 7', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 5, 'pack' => 'علب ×50', 'expiry' => '2030-10-01', 'expiry_text' => '10/2020', 'notes' => null, 'needs_review' => false, 'or_count' => '1 مفتوحه'],
        ['no' => 137, 'name' => 'جوانتي مقاس 8', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 10, 'pack' => 'علب ×50', 'expiry' => '2030-10-01', 'expiry_text' => '10/2020', 'notes' => null, 'needs_review' => false, 'or_count' => '1 مفتوحه'],
        ['no' => 138, 'name' => 'جوانتي مقاس 7.5', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 2, 'pack' => 'علب', 'expiry' => '2031-11-01', 'expiry_text' => '11/2021', 'notes' => null, 'needs_review' => false, 'or_count' => '1 + 1 مفتوحه'],
        ['no' => 139, 'name' => 'كمامات برباط', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 9, 'pack' => 'علب', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 140, 'name' => 'كمامات استك', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 5, 'pack' => 'علب', 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 141, 'name' => 'جوانتي (gloves)', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 5, 'pack' => 'علب', 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 142, 'name' => 'ودانات', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 12, 'pack' => 'علبة', 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 143, 'name' => 'بروبوفول', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Box, 'quantity' => 27, 'pack' => 'علب (5 ×3)', 'expiry' => '2028-01-01', 'expiry_text' => '2028', 'notes' => null, 'needs_review' => false, 'or_count' => null],
        ['no' => 144, 'name' => 'مرهم', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 0, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => true, 'or_count' => '4'],
        ['no' => 145, 'name' => 'قطره مضاد حيوي', 'category' => ItemCategory::Medical, 'unit' => ItemUnit::Piece, 'quantity' => 0, 'pack' => null, 'expiry' => null, 'expiry_text' => null, 'notes' => null, 'needs_review' => true, 'or_count' => '4'],
    ];

    /** @var array<string, InventoryItem> normalized name → oldest item with that name */
    private array $itemsByName = [];

    /** @var array<string, InventoryItem> "type|power" → oldest lens item */
    private array $lensesByKey = [];

    /** @var list<string> */
    private array $warnings = [];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->indexExistingItems();

            /** @var array<string, array{item: InventoryItem, quantity: float, expiry: ?string, notes: list<string>}> $counted */
            $counted = [];
            $created = 0;

            foreach (self::LENSES as $lens) {
                $isMinus = $lens['type'] === self::MINUS_LENS_TYPE;
                $power = $this->powerKey(($isMinus ? '-' : '').$lens['power']);
                $item = $this->lensesByKey[self::LENS_TYPES[$lens['type']].'|'.$power] ?? null;

                if ($item === null) {
                    $item = $this->createItem(
                        ($isMinus ? 'عدسة لينة' : 'عدسة '.$lens['type']).' باور '.$power,
                        ItemCategory::Medical,
                        ItemUnit::Piece,
                    );
                    $created++;
                }

                $this->accumulate($counted, $item, $lens['quantity'], $lens, null);
            }

            foreach (self::SUPPLIES as $supply) {
                $item = $this->itemsByName[$this->normalizeArabic(self::SUPPLY_ALIASES[$supply['no']] ?? $supply['name'])]
                    ?? $this->itemsByName[$this->normalizeArabic($supply['name'])]
                    ?? null;

                $packSize = self::PACK_SIZES[$supply['no']] ?? null;

                if ($item === null) {
                    $item = $this->createItem(
                        $supply['name'],
                        $supply['category'],
                        $packSize !== null ? ItemUnit::Piece : $supply['unit'],
                    );
                    $created++;
                }

                $this->accumulate($counted, $item, $supply['quantity'] * ($packSize ?? 1), $supply, $supply['pack']);
            }

            $adjustments = $this->applyCounts($counted);

            if ($adjustments !== []) {
                app(StockTakeAdjustmentAction::class)->execute($adjustments, self::PERMIT_NOTES);
            }

            $this->command?->info(sprintf(
                'Stock take: %d sheet items → %d inventory items, %d created, %d quantities adjusted.',
                count(self::LENSES) + count(self::SUPPLIES),
                count($counted),
                $created,
                count($adjustments),
            ));
        });

        foreach ($this->warnings as $warning) {
            $this->command?->warn($warning);
        }
    }

    private function indexExistingItems(): void
    {
        foreach (InventoryItem::orderBy('created_at')->orderBy('id')->get() as $item) {
            $name = $this->normalizeArabic($item->name);

            if (isset($this->itemsByName[$name])) {
                $this->warnings[] = "Duplicate item name «{$item->name}» — the stock count goes to the oldest one.";
            } else {
                $this->itemsByName[$name] = $item;
            }

            $lensKey = $this->lensKeyFromName($name);

            if ($lensKey === null) {
                continue;
            }

            if (isset($this->lensesByKey[$lensKey])) {
                $this->warnings[] = "«{$item->name}» and «{$this->lensesByKey[$lensKey]->name}» are both lens {$lensKey} — the stock count goes to the older one.";
            } else {
                $this->lensesByKey[$lensKey] = $item;
            }
        }
    }

    /**
     * "type|power" for an item named like «عدسه لينه 23» / «عدسة صلبة باور 1» / «عدسه امريكي انفستا 12».
     */
    private function lensKeyFromName(string $normalizedName): ?string
    {
        if (! str_starts_with($normalizedName, 'عدسه') || ! preg_match('/(-?\d+(?:\.\d+)?)$/u', $normalizedName, $power)) {
            return null;
        }

        foreach (self::LENS_TYPES as $typeKey) {
            if (str_contains($normalizedName, $typeKey)) {
                return $typeKey.'|'.$this->powerKey($power[1]);
            }
        }

        foreach (self::AMERICAN_LENS_BRANDS as $brand) {
            if (str_contains($normalizedName, $brand)) {
                return 'امريكي|'.$this->powerKey($power[1]);
            }
        }

        return null;
    }

    private function powerKey(string $power): string
    {
        return (string) (float) $power;
    }

    private function createItem(string $name, ItemCategory $category, ItemUnit $unit): InventoryItem
    {
        $item = InventoryItem::create([
            'name' => $name,
            'category' => $category,
            'unit' => $unit,
            'quantity' => 0,
            'is_active' => true,
        ]);

        $this->itemsByName[$this->normalizeArabic($name)] = $item;

        if ($lensKey = $this->lensKeyFromName($this->normalizeArabic($name))) {
            $this->lensesByKey[$lensKey] = $item;
        }

        return $item;
    }

    /**
     * @param  array<string, array{item: InventoryItem, quantity: float, expiry: ?string, notes: list<string>}>  $counted
     * @param  array{quantity: int|float, expiry: ?string, expiry_text: ?string, notes: ?string, needs_review: bool, or_count: ?string}  $row
     */
    private function accumulate(array &$counted, InventoryItem $item, int|float $storeQuantity, array $row, ?string $pack): void
    {
        $quantity = (float) $storeQuantity;
        $notes = [];

        if ($row['or_count'] !== null && is_numeric($row['or_count'])) {
            $quantity += (float) $row['or_count'];
        } elseif ($row['or_count'] !== null) {
            $notes[] = "جرد العمليات: {$row['or_count']}";
        }

        if ($pack !== null) {
            $notes[] = "العبوة: {$pack}";
        }

        if ($row['expiry_text'] !== null) {
            $notes[] = "الصلاحية في الجرد: {$row['expiry_text']}";
        }

        if ($row['notes'] !== null) {
            $notes[] = $row['notes'];
        }

        if ($row['needs_review'] || ($row['or_count'] !== null && ! is_numeric($row['or_count']))) {
            $notes[] = 'محتاج مراجعة مع ورق الجرد';
        }

        $entry = $counted[$item->id] ?? ['item' => $item, 'quantity' => 0.0, 'expiry' => null, 'notes' => []];
        $entry['quantity'] += $quantity;
        $entry['expiry'] = collect([$entry['expiry'], $row['expiry']])->filter()->min();

        if ($notes !== []) {
            $entry['notes'][] = 'جرد يدوي: '.implode(' — ', $notes);
        }

        $counted[$item->id] = $entry;
    }

    /**
     * Writes expiry dates and count notes, and returns the quantity changes for the stock-take permit.
     *
     * @param  array<string, array{item: InventoryItem, quantity: float, expiry: ?string, notes: list<string>}>  $counted
     * @return list<array{item_id: string, physical_qty: float}>
     */
    private function applyCounts(array $counted): array
    {
        $adjustments = [];

        foreach ($counted as $entry) {
            $item = $entry['item']->fresh();
            $notes = (string) $item->notes;

            foreach ($entry['notes'] as $line) {
                if (! str_contains($notes, $line)) {
                    $notes = trim($notes."\n".$line);
                }
            }

            $item->update([
                'expiry_date' => $entry['expiry'] ?? $item->expiry_date,
                'notes' => $notes === '' ? null : $notes,
            ]);

            if (abs((float) $item->quantity - $entry['quantity']) > 0.001) {
                $adjustments[] = ['item_id' => $item->id, 'physical_qty' => $entry['quantity']];
            }
        }

        return $adjustments;
    }
}
