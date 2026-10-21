<?php

namespace App\Queries;

use App\Enums\CashSessionStatus;
use App\Enums\CollectionStatus;
use App\Models\CashSession;
use App\Models\CollectionEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class CashSessionQuery
{
    /**
     * @return Builder<CashSession>
     */
    public function for(User $user): Builder
    {
        $membership = $user->currentMembership();

        abort_if($membership === null, 403);

        $query = CashSession::query();

        Gate::forUser($user)->authorize('viewAny', CashSession::class);

        if (! $membership->role->canApprove() && ! $membership->role->sells()) {
            abort(403);
        }

        if ($membership->role->sells()) {
            $query->where('seller_user_id', $user->id);
        }

        return $query->with(['seller:id,name', 'approver:id,name', 'items']);
    }

    /**
     * @param  array{search?: string, status?: string, seller_user_id?: string, currency?: string, from?: string, to?: string}  $filters
     * @return Builder<CashSession>
     */
    public function filtered(User $user, array $filters = []): Builder
    {
        $query = $this->for($user)->reorder();
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->whereHas('seller', fn (Builder $seller): Builder => $seller
                ->whereLike('name', '%'.$search.'%'));
        }

        $status = (string) ($filters['status'] ?? '');

        if (in_array($status, CashSessionStatus::values(), true)) {
            $query->where('status', $status);
        }

        if (($filters['seller_user_id'] ?? '') !== '' && $this->isApprover($user)) {
            $query->where('seller_user_id', $filters['seller_user_id']);
        }

        if (($filters['currency'] ?? '') !== '') {
            $query->where('currency', strtoupper((string) $filters['currency']));
        }

        if (($filters['from'] ?? '') !== '') {
            $query->whereDate('opened_at', '>=', $filters['from']);
        }

        if (($filters['to'] ?? '') !== '') {
            $query->whereDate('opened_at', '<=', $filters['to']);
        }

        return $query->orderByDesc('opened_at')->orderBy('id');
    }

    /**
     * @return array{counts: array<string, int>, totals: Collection<int, \stdClass>}
     */
    public function summary(User $user): array
    {
        $query = $this->for($user)->reorder();

        $rows = (clone $query)
            ->toBase()
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $counts = [];

        foreach (CashSessionStatus::cases() as $status) {
            $counts[$status->value] = (int) ($rows[$status->value] ?? 0);
        }

        $totals = (clone $query)
            ->toBase()
            ->select('currency')
            ->selectRaw('sum(expected_amount) as expected')
            ->selectRaw('sum(counted_amount) as counted')
            ->selectRaw('sum(difference) as difference')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        return [
            'counts' => $counts,
            'totals' => $totals,
        ];
    }

    /**
     * @return Collection<int, CollectionEvent>
     */
    public function availableCollections(User $actor, CashSession $session): Collection
    {
        abort_unless($session->tenant_id === $actor->currentTenantId(), 403);

        Gate::forUser($actor)->authorize('view', $session);

        return CollectionEvent::query()
            ->where('seller_user_id', $session->seller_user_id)
            ->where('currency', $session->currency)
            ->where('status', CollectionStatus::Pending)
            ->whereDoesntHave('cashSessionItems')
            ->with(['invoice.customer'])
            ->orderByDesc('collected_on')
            ->orderBy('id')
            ->limit(100)
            ->get();
    }

    public function openSessionFor(User $user, string $currency): ?CashSession
    {
        return CashSession::query()
            ->where('seller_user_id', $user->id)
            ->where('currency', strtoupper($currency))
            ->where('status', CashSessionStatus::Open)
            ->first();
    }

    private function isApprover(User $user): bool
    {
        return $user->currentMembership()?->role->canApprove() ?? false;
    }
}
