<?php

namespace Modules\Booking\States;

use Modules\Admin\Models\Setting;
use Modules\Booking\Models\Booking;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @extends State<Booking>
 */
abstract class BookingStatus extends State
{
    /**
     * @var array<int, class-string<BookingStatus>>
     */
    private const STATE_CLASSES = [
        WaitingState::class,
        ConfirmedState::class,
        InProgressState::class,
        CompletedState::class,
        CancelledState::class,
    ];

    /**
     * Arabic labels indexed by status name, used for settings UI and shared props
     * without needing to instantiate a State (which requires a model).
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'waiting' => 'في الانتظار',
        'confirmed' => 'مؤكد',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
    ];

    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(WaitingState::class)
            ->allowTransition(WaitingState::class, ConfirmedState::class)
            ->allowTransition(ConfirmedState::class, InProgressState::class)
            ->allowTransition(InProgressState::class, CompletedState::class)
            ->allowTransition(
                [WaitingState::class, ConfirmedState::class, InProgressState::class],
                CancelledState::class
            );
    }

    /**
     * Setting key that toggles a status on/off across the whole system.
     */
    public static function settingKey(string $status): string
    {
        return "booking_status_visible_{$status}";
    }

    /**
     * Whether the given status is currently visible across the system.
     * Defaults to true so unseeded statuses stay usable.
     */
    public static function isVisible(string $status): bool
    {
        return Setting::getValue(self::settingKey($status), 'true') !== 'false';
    }

    /**
     * All statuses with metadata for settings UI and shared props.
     *
     * @return array<int, array{value: string, label: string, visible: bool}>
     */
    public static function options(): array
    {
        return array_map(static function (string $class): array {
            $name = $class::$name;

            return [
                'value' => $name,
                'label' => self::STATUS_LABELS[$name] ?? $name,
                'visible' => self::isVisible($name),
            ];
        }, self::STATE_CLASSES);
    }

    /**
     * Map of status name => visible flag, for sharing to the frontend.
     *
     * @return array<string, bool>
     */
    public static function visibilityMap(): array
    {
        $map = [];
        foreach (self::STATE_CLASSES as $class) {
            $name = $class::$name;
            $map[$name] = self::isVisible($name);
        }

        return $map;
    }

    /**
     * Status names that are currently visible.
     *
     * @return array<int, string>
     */
    public static function visibleStatusNames(): array
    {
        $names = [];
        foreach (self::STATE_CLASSES as $class) {
            $name = $class::$name;
            if (self::isVisible($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
