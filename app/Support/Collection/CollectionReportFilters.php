<?php

namespace App\Support\Collection;

use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class CollectionReportFilters
{
    /**
     * @return array{from: string, to: string, status: string, payment_method: list<string>, seller_user_id: list<string>, currency: list<string>}
     */
    public static function fromRequest(Request $request): array
    {
        $status = (string) $request->query('status', CollectionStatus::Accepted->value);

        if (! in_array($status, CollectionStatus::values(), true)) {
            $status = CollectionStatus::Accepted->value;
        }

        return [
            'from' => self::date($request->query('from')) ?? now()->toDateString(),
            'to' => self::date($request->query('to')) ?? now()->toDateString(),
            'status' => $status,
            'payment_method' => self::values($request->query('payment_method'), array_values(PaymentMethod::values())),
            'seller_user_id' => self::values($request->query('seller_user_id')),
            'currency' => self::values($request->query('currency')),
        ];
    }

    private static function date(mixed $value): ?string
    {
        return is_string($value) && Carbon::hasFormat($value, 'Y-m-d') ? $value : null;
    }

    /**
     * @param  list<string>|null  $allowed
     * @return list<string>
     */
    private static function values(mixed $value, ?array $allowed = null): array
    {
        $values = is_array($value) ? $value : [$value];
        $normalized = [];

        foreach ($values as $item) {
            if (! is_string($item)) {
                continue;
            }

            $item = trim($item);

            if ($item === '' || in_array($item, $normalized, true)) {
                continue;
            }

            if ($allowed !== null && ! in_array($item, $allowed, true)) {
                continue;
            }

            $normalized[] = $item;
        }

        return $normalized;
    }
}
