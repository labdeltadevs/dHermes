<?php

namespace App\Http\Controllers;

use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use App\Queries\CollectionQuery;
use App\Support\Collection\CollectionReportFilters;
use App\Support\Collection\CollectionReportPdfGenerator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CollectionReportPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $filters = CollectionReportFilters::fromRequest($request);
        $events = app(CollectionQuery::class)->filtered($user, $filters)->get();

        $tenantName = $user->currentTenantId() !== null
            ? Tenant::query()->whereKey($user->currentTenantId())->value('name')
            : null;

        $sellerNames = $filters['seller_user_id'] === []
            ? null
            : User::query()
                ->whereIn('id', $filters['seller_user_id'])
                ->orderBy('name')
                ->pluck('name')
                ->implode(', ');

        $statusLabel = $filters['status'] !== ''
            ? CollectionStatus::tryFrom($filters['status'])?->label()
            : null;

        $paymentMethodLabels = collect($filters['payment_method'])
            ->map(fn (string $method): ?string => PaymentMethod::tryFrom($method)?->label())
            ->filter()
            ->implode(', ');

        $pdf = app(CollectionReportPdfGenerator::class)->generate(
            $events,
            $filters,
            is_string($tenantName) ? $tenantName : null,
            $sellerNames === '' ? null : $sellerNames,
            $statusLabel,
            $paymentMethodLabels === '' ? null : $paymentMethodLabels,
            (string) $user->name,
        );

        $filename = 'cobranzas_'.$filters['from'].'_a_'.$filters['to'].'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
