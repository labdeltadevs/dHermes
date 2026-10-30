<?php

namespace App\Queries;

use App\Enums\CollectionStatus;
use App\Enums\TenantRole;
use App\Models\CollectionEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class CollectionQuery
{
    /**
     * @return Builder<CollectionEvent>
     */
    public function for(User $user): Builder
    {
        $membership = $user->currentMembership();

        abort_if($membership === null, 403);

        $query = CollectionEvent::query()->with([
            'invoice.customer',
            'invoice.salesRep',
            'seller',
            'reviewedBy:id,name',
        ]);

        if ($membership->role === TenantRole::Seller || $membership->role === TenantRole::Collector) {
            $query->where('seller_user_id', $user->id);
        } elseif (! $membership->role->canApprove()) {
            abort(403);
        }

        Gate::forUser($user)->authorize('viewAny', CollectionEvent::class);

        return $query;
    }

    /**
     * @param  array{search?: string, status?: string, seller_user_id?: string, currency?: string, from?: string, to?: string}  $filters
     * @return Builder<CollectionEvent>
     */
    public function filtered(User $user, array $filters = []): Builder
    {
        $query = $this->for($user)->reorder();
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('erp_docnum', '%'.$search.'%')
                    ->orWhereLike('voucher_code', '%'.$search.'%')
                    ->orWhereLike('flash_invoice_number', '%'.$search.'%')
                    ->orWhereLike('flash_cardcode', '%'.$search.'%')
                    ->orWhereLike('flash_customer_name', '%'.$search.'%')
                    ->orWhereHas('invoice', function (Builder $invoice) use ($search): void {
                        $invoice->where(function (Builder $invoice) use ($search): void {
                            $invoice->whereLike('number', '%'.$search.'%')
                                ->orWhereLike('docentry', '%'.$search.'%')
                                ->orWhereHas('customer', fn (Builder $customer): Builder => $customer
                                    ->whereLike('name', '%'.$search.'%')
                                    ->orWhereLike('cardcode', '%'.$search.'%'));
                        });
                    });
            });
        }

        $status = (string) ($filters['status'] ?? '');

        if (in_array($status, CollectionStatus::values(), true)) {
            $query->where('status', $status);
        }

        $isApprover = $user->currentMembership()?->role->canApprove() ?? false;

        if ($isApprover && ($filters['seller_user_id'] ?? '') !== '') {
            $query->where('seller_user_id', $filters['seller_user_id']);
        }

        if (($filters['currency'] ?? '') !== '') {
            $query->where('currency', $filters['currency']);
        }

        if (($filters['from'] ?? '') !== '') {
            $query->whereDate('collected_on', '>=', $filters['from']);
        }

        if (($filters['to'] ?? '') !== '') {
            $query->whereDate('collected_on', '<=', $filters['to']);
        }

        return $query->orderByDesc('collected_on')->orderBy('id');
    }

    /**
     * @param  array{search?: string, status?: string, seller_user_id?: string, currency?: string, from?: string, to?: string}  $filters
     * @return Collection<int, \stdClass>
     */
    public function summaryByCurrency(User $user, array $filters = []): Collection
    {
        return $this->filtered($user, $filters)
            ->reorder()
            ->toBase()
            ->select('currency')
            ->selectRaw('count(*) as collections_count')
            ->selectRaw('sum(amount) as total')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as pending_count', [CollectionStatus::Pending->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as accepted_count', [CollectionStatus::Accepted->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as rejected_count', [CollectionStatus::Rejected->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as cancelled_count', [CollectionStatus::Cancelled->value])
            ->selectRaw('sum(case when status = ? then amount else 0 end) as accepted_total', [CollectionStatus::Accepted->value])
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();
    }
}
