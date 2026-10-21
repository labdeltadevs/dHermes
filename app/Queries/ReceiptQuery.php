<?php

namespace App\Queries;

use App\Enums\TenantRole;
use App\Models\Receipt;
use App\Models\ReceiptApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ReceiptQuery
{
    /**
     * @return Builder<Receipt>
     */
    public function for(User $user): Builder
    {
        $membership = $user->currentMembership();

        abort_if($membership === null, 403);

        $query = Receipt::query()
            ->where('cancelled', false);

        if ($membership->role === TenantRole::Seller) {
            abort_if($membership->sales_rep_id === null, 403);

            $query->whereHas('applications.invoice', fn (Builder $invoice): Builder => $invoice
                ->where('sales_rep_id', $membership->sales_rep_id));
        } elseif ($membership->role === TenantRole::Collector) {
            $query->whereHas('customer', fn (Builder $customer): Builder => $customer
                ->where('collector_user_id', $user->id));
        } elseif (! $membership->role->canApprove()) {
            abort(403);
        }

        Gate::forUser($user)->authorize('viewAny', Receipt::class);

        return $query->with('customer:id,name,cardcode,sales_rep_id');
    }

    /**
     * @param  array{search?: string, sales_rep_id?: string, from?: string, to?: string, sort?: string}  $filters
     * @return Builder<Receipt>
     */
    public function filtered(User $user, array $filters = []): Builder
    {
        $query = $this->for($user)->reorder();
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('number', '%'.$search.'%')
                    ->orWhereLike('docentry', '%'.$search.'%')
                    ->orWhereHas('customer', fn (Builder $customer): Builder => $customer
                        ->whereLike('name', '%'.$search.'%')
                        ->orWhereLike('cardcode', '%'.$search.'%'));
            });
        }

        $isApprover = $user->currentMembership()?->role->canApprove() ?? false;

        if ($isApprover && ($filters['sales_rep_id'] ?? '') !== '') {
            $query->whereHas('customer', fn (Builder $customer): Builder => $customer
                ->where('sales_rep_id', $filters['sales_rep_id']));
        }

        if (($filters['from'] ?? '') !== '') {
            $query->whereDate('receipt_date', '>=', $filters['from']);
        }

        if (($filters['to'] ?? '') !== '') {
            $query->whereDate('receipt_date', '<=', $filters['to']);
        }

        $query->select('receipts.*');
        $attributedRepId = $this->attributedRepId($user);

        if ($attributedRepId === null) {
            $query->addSelect(DB::raw('receipts.total as attributed_total'));
        } else {
            $query->addSelect(['attributed_total' => $this->appliedAmountSubquery($attributedRepId)]);
        }

        $sort = (string) ($filters['sort'] ?? 'receipt_date_desc');

        match ($sort) {
            'receipt_date_asc' => $query->orderBy('receipt_date'),
            'total_desc' => $query->orderByDesc('attributed_total'),
            default => $query->orderByDesc('receipt_date'),
        };

        return $query->with('customer.salesRep:id,name,slpcode')->orderBy('id');
    }

    /**
     * @param  array{search?: string, sales_rep_id?: string, from?: string, to?: string, sort?: string}  $filters
     * @return Collection<int, \stdClass>
     */
    public function summaryByCurrency(User $user, array $filters = []): Collection
    {
        $query = $this->filtered($user, $filters)->reorder();

        if ($this->attributedRepId($user) === null) {
            return $query->toBase()
                ->select('currency')
                ->selectRaw('count(*) as receipts_count')
                ->selectRaw('sum(receipts.total) as total')
                ->groupBy('currency')
                ->orderBy('currency')
                ->get();
        }

        return DB::query()
            ->fromSub($query->toBase(), 'receipts')
            ->select('currency')
            ->selectRaw('count(*) as receipts_count')
            ->selectRaw('sum(attributed_total) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();
    }

    private function attributedRepId(User $user): ?string
    {
        $membership = $user->currentMembership();

        abort_if($membership === null, 403);

        return $membership->role === TenantRole::Seller ? $membership->sales_rep_id : null;
    }

    /**
     * @return Builder<ReceiptApplication>
     */
    private function appliedAmountSubquery(string $salesRepId): Builder
    {
        return ReceiptApplication::query()
            ->selectRaw('coalesce(sum(receipt_applications.applied_amount), 0)')
            ->join('invoices', function (JoinClause $join): void {
                $join->on('invoices.id', '=', 'receipt_applications.invoice_id')
                    ->on('invoices.tenant_id', '=', 'receipt_applications.tenant_id');
            })
            ->whereColumn('receipt_applications.receipt_id', 'receipts.id')
            ->where('receipt_applications.invtype', '13')
            ->where('invoices.sales_rep_id', $salesRepId);
    }
}
