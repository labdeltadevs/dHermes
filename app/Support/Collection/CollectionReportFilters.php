<?php

namespace App\Support\Collection;

use App\Enums\CollectionStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class CollectionReportFilters
{
    /**
     * @return array{from: string, to: string, status: string, payment_method: string, seller_user_id: string, currency: string}
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
            'payment_method' => (string) $request->query('payment_method', ''),
            'seller_user_id' => (string) $request->query('seller_user_id', ''),
            'currency' => (string) $request->query('currency', ''),
        ];
    }

    private static function date(mixed $value): ?string
    {
        return is_string($value) && Carbon::hasFormat($value, 'Y-m-d') ? $value : null;
    }
}
