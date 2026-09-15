<?php

namespace App\Services;

use App\Models\Platform;

/**
 * Turns a platform's stored charge structure into concrete charges for an amount.
 *
 * This is the single source of truth for "what will this marketplace deduct?":
 * both the order form (via the charge-preview endpoint) and the CSV importer use it,
 * so a marketplace's fees are never typed twice or guessed.
 */
class PlatformChargeCalculator
{
    /**
     * Charge names in the order they should be displayed, mapped to the
     * charge_structure key that drives them.
     */
    public const FEE_KEYS = [
        'commission' => 'commission_percent',
        'shipping' => 'shipping_fee',
        'closing' => 'closing_fee',
        'other' => 'other_fee',
    ];

    /**
     * Compute the charges for a single line item of $amount.
     *
     * Percentage keys are applied to the line amount; flat keys are added as-is.
     * GST is charged on the platform's own fees (commission + shipping + closing + other),
     * which is how marketplaces actually bill it — not on the product value.
     *
     * @return array{charges: array<int, array{charge_name: string, amount: float}>, total: float, net: float, amount: float}
     */
    public function forAmount(Platform $platform, float $amount): array
    {
        $amount = round(max($amount, 0), 2);

        $commission = $this->percent($platform, 'commission_percent', $amount);
        $shipping = $this->flat($platform, 'shipping_fee');
        $closing = $this->flat($platform, 'closing_fee');
        $other = $this->flat($platform, 'other_fee');

        $gst = $this->percent($platform, 'gst_percent', $commission + $shipping + $closing + $other);

        $values = [
            'commission' => $commission,
            'shipping' => $shipping,
            'closing' => $closing,
            'gst' => $gst,
            'other' => $other,
        ];

        $charges = [];
        foreach ($values as $name => $value) {
            if ($value > 0) {
                $charges[] = ['charge_name' => $name, 'amount' => $value];
            }
        }

        $total = round(array_sum($values), 2);

        return [
            'amount' => $amount,
            'charges' => $charges,
            'total' => $total,
            'net' => round($amount - $total, 2),
        ];
    }

    /**
     * Whether this platform has anything configured that would produce charges.
     */
    public function hasFees(Platform $platform): bool
    {
        foreach (array_values(self::FEE_KEYS) as $key) {
            if ($platform->getChargeValue($key) > 0) {
                return true;
            }
        }

        return $platform->getChargeValue('gst_percent') > 0;
    }

    /**
     * A short human summary of the structure, e.g. "5% + ₹45 ship + ₹25 close + 18% GST".
     * Used in the order form dropdown so the platform choice is informed.
     */
    public function describe(Platform $platform): string
    {
        $parts = [];

        if (($commission = $platform->getChargeValue('commission_percent')) > 0) {
            $parts[] = $this->trimNumber($commission).'% commission';
        }
        if (($shipping = $platform->getChargeValue('shipping_fee')) > 0) {
            $parts[] = '₹'.$this->trimNumber($shipping).' shipping';
        }
        if (($closing = $platform->getChargeValue('closing_fee')) > 0) {
            $parts[] = '₹'.$this->trimNumber($closing).' closing';
        }
        if (($gst = $platform->getChargeValue('gst_percent')) > 0) {
            $parts[] = $this->trimNumber($gst).'% GST';
        }
        if (($other = $platform->getChargeValue('other_fee')) > 0) {
            $parts[] = '₹'.$this->trimNumber($other).' other';
        }

        return implode(' + ', $parts);
    }

    protected function percent(Platform $platform, string $key, float $base): float
    {
        if ($base <= 0) {
            return 0.0;
        }

        return round($base * $platform->getChargeValue($key) / 100, 2);
    }

    protected function flat(Platform $platform, string $key): float
    {
        return round($platform->getChargeValue($key), 2);
    }

    protected function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
