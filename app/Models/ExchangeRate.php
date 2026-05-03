<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps  = false;
    protected $dates    = ['updated_at'];

    protected $fillable = ['base_currency', 'target_currency', 'rate', 'source'];
    protected $casts    = ['rate' => 'float'];

    public static function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to) return $amount;

        $rate = static::where('base_currency', $from)
            ->where('target_currency', $to)
            ->value('rate');

        if (!$rate) {
            $rev  = static::where('base_currency', $to)->where('target_currency', $from)->value('rate');
            $rate = $rev ? (1 / $rev) : 1.0;
        }

        return round($amount * $rate, 2);
    }

    public static function toPhp(float $amount, string $currency): float
    {
        return static::convert($amount, $currency, 'PHP');
    }

    public static function fromPhp(float $phpAmount, string $targetCurrency): float
    {
        return static::convert($phpAmount, 'PHP', $targetCurrency);
    }
}
