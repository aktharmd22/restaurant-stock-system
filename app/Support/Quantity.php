<?php

namespace App\Support;

use App\Models\Item;
use InvalidArgumentException;

/**
 * A quantity that knows what it is a quantity OF.
 *
 * The single worst bug in an inventory system is a bare number: a "5" that
 * means 5 kg in one place and 5 g in another. It corrupts data silently and is
 * almost impossible to trace afterwards. So no service in this app accepts a
 * plain number for a quantity - it accepts one of these.
 *
 * Internally this is always a whole number of BASE units (grams, millilitres,
 * pieces). Never a float: floats accumulate rounding error, and a ledger whose
 * sum no longer equals its balance is worse than useless.
 */
final class Quantity
{
    private function __construct(
        public readonly int $baseUnits,
        public readonly Item $item,
    ) {
    }

    public static function fromBase(int $baseUnits, Item $item): self
    {
        return new self($baseUnits, $item);
    }

    /**
     * From what a person typed or tapped: 2.5 (kg) -> 2500 (g).
     */
    public static function fromOrderUnit(float|int|string $amount, Item $item): self
    {
        $amount = (float) $amount;

        if (! is_finite($amount)) {
            throw new InvalidArgumentException('Quantity must be a real number.');
        }

        return new self((int) round($amount * $item->conversion_factor), $item);
    }

    public static function zero(Item $item): self
    {
        return new self(0, $item);
    }

    /** The number a person sees: 2500 (g) -> 2.5 (kg). */
    public function toOrderUnit(): float
    {
        $factor = max(1, $this->item->conversion_factor);

        return round($this->baseUnits / $factor, 3);
    }

    /** "2.5 kg", "750 ml", "12 pcs" */
    public function forDisplay(): string
    {
        $value = $this->toOrderUnit();
        $formatted = rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');

        return trim($formatted.' '.$this->unitLabel());
    }

    public function unitLabel(): string
    {
        return match ($this->item->order_unit) {
            'piece' => abs($this->toOrderUnit()) === 1.0 ? 'pc' : 'pcs',
            'dozen' => 'dz',
            'litre' => 'L',
            'packet' => abs($this->toOrderUnit()) === 1.0 ? 'packet' : 'packets',
            'sack' => abs($this->toOrderUnit()) === 1.0 ? 'sack' : 'sacks',
            default => (string) $this->item->order_unit,
        };
    }

    public function plus(self $other): self
    {
        $this->assertSameItem($other);

        return new self($this->baseUnits + $other->baseUnits, $this->item);
    }

    public function minus(self $other): self
    {
        $this->assertSameItem($other);

        return new self($this->baseUnits - $other->baseUnits, $this->item);
    }

    public function negated(): self
    {
        return new self(-$this->baseUnits, $this->item);
    }

    public function isZero(): bool
    {
        return $this->baseUnits === 0;
    }

    public function isPositive(): bool
    {
        return $this->baseUnits > 0;
    }

    public function isNegative(): bool
    {
        return $this->baseUnits < 0;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameItem($other);

        return $this->baseUnits > $other->baseUnits;
    }

    public function equals(self $other): bool
    {
        return $this->item->id === $other->item->id && $this->baseUnits === $other->baseUnits;
    }

    private function assertSameItem(self $other): void
    {
        if ($this->item->id !== $other->item->id) {
            throw new InvalidArgumentException(
                'Cannot combine quantities of different items: '
                ."{$this->item->name} and {$other->item->name}.",
            );
        }
    }
}
