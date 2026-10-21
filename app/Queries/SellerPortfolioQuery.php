<?php

namespace App\Queries;

use App\Enums\TenantRole;
use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class SellerPortfolioQuery
{
    /**
     * @return Builder<Invoice>
     */
    public function for(User $user): Builder
    {
        $membership = $user->currentMembership();

        abort_if($membership === null, 403);

        $query = Invoice::query()
            ->where('cancelled', false)
            ->where('balance', '>', 0);

        if ($membership->role === TenantRole::Seller) {
            abort_if($membership->sales_rep_id === null, 403);

            $query->where('sales_rep_id', $membership->sales_rep_id);
        } elseif ($membership->role === TenantRole::Collector) {
            $query->whereHas('customer', fn (Builder $customer): Builder => $customer
                ->where('collector_user_id', $user->id));
        } elseif (! $membership->role->canApprove()) {
            abort(403);
        }

        Gate::forUser($user)->authorize('viewAny', Invoice::class);

        return $query->with('customer:id,name,cardcode');
    }

    /**
     * @param  array{search?: string, sales_rep_id?: string, due?: string, sort?: string, today?: string}  $filters
     * @return Builder<Invoice>
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

        if (($filters['sales_rep_id'] ?? '') !== '') {
            $query->where('sales_rep_id', $filters['sales_rep_id']);
        }

        $today = (string) ($filters['today'] ?? now()->toDateString());

        match ($filters['due'] ?? '') {
            'overdue' => $query->whereDate('due_date', '<', $today),
            'due_soon' => $query->whereBetween('due_date', [
                $today,
                CarbonImmutable::parse($today)->addDays(7)->toDateString(),
            ]),
            'current' => $query->whereDate('due_date', '>=', $today),
            default => null,
        };

        $sort = (string) ($filters['sort'] ?? 'due_asc');

        match ($sort) {
            'due_desc' => $query->orderByDesc('due_date'),
            'balance_desc' => $query->orderByDesc('balance'),
            'issue_desc' => $query->orderByDesc('issue_date'),
            default => $query->orderBy('due_date'),
        };

        return $query->with('salesRep:id,name,slpcode')->orderBy('id');
    }

    /**
     * @param  array{search?: string, sales_rep_id?: string, due?: string, sort?: string, today?: string}  $filters
     * @return Collection<int, \stdClass>
     */
    public function summaryByCurrency(User $user, array $filters = []): Collection
    {
        $today = (string) ($filters['today'] ?? now()->toDateString());

        return $this->filtered($user, $filters)
            ->reorder()
            ->toBase()
            ->select('currency')
            ->selectRaw('count(*) as invoice_count')
            ->selectRaw('sum(balance) as balance')
            ->selectRaw('count(case when due_date < ? then 1 end) as overdue_count', [$today])
            ->selectRaw('sum(case when due_date < ? then balance else 0 end) as overdue_balance', [$today])
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();
    }

    /**
     * @param  array{search?: string, sales_rep_id?: string, due?: string, today?: string, currency?: string}  $filters
     * @return Collection<int, \stdClass>
     */
    public function summaryBySalesRep(User $user, array $filters = []): Collection
    {
        $today = (string) ($filters['today'] ?? now()->toDateString());
        $search = trim((string) ($filters['search'] ?? ''));

        $filters['search'] = '';

        $query = $this->filtered($user, $filters)
            ->reorder()
            ->setEagerLoads([]);

        $query->join('sales_reps', function (JoinClause $join): void {
            $join->on('sales_reps.id', '=', 'invoices.sales_rep_id')
                ->on('sales_reps.tenant_id', '=', 'invoices.tenant_id');
        });

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('invoices.number', '%'.$search.'%')
                    ->orWhereLike('invoices.docentry', '%'.$search.'%')
                    ->orWhereLike('sales_reps.name', '%'.$search.'%')
                    ->orWhereLike('sales_reps.slpcode', '%'.$search.'%')
                    ->orWhereHas('customer', fn (Builder $customer): Builder => $customer
                        ->whereLike('name', '%'.$search.'%')
                        ->orWhereLike('cardcode', '%'.$search.'%'));
            });
        }

        if (($filters['currency'] ?? '') !== '') {
            $query->where('invoices.currency', $filters['currency']);
        }

        return $query
            ->toBase()
            ->whereNotNull('invoices.sales_rep_id')
            ->select('invoices.sales_rep_id')
            ->selectRaw('sales_reps.name as sales_rep_name')
            ->selectRaw('sales_reps.slpcode as slpcode')
            ->selectRaw('invoices.currency as currency')
            ->selectRaw('count(*) as invoice_count')
            ->selectRaw('sum(invoices.balance) as balance')
            ->selectRaw('count(case when invoices.due_date < ? then 1 end) as overdue_count', [$today])
            ->selectRaw('sum(case when invoices.due_date < ? then invoices.balance else 0 end) as overdue_balance', [$today])
            ->selectRaw('count(distinct invoices.customer_id) as customer_count')
            ->groupBy('invoices.sales_rep_id', 'sales_reps.name', 'sales_reps.slpcode', 'invoices.currency')
            ->orderByRaw('sum(invoices.balance) desc')
            ->orderBy('sales_reps.name')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    public function totalsByCurrency(User $user): array
    {
        return $this->for($user)
            ->reorder()
            ->selectRaw('currency, SUM(balance) as aggregate')
            ->groupBy('currency')
            ->pluck('aggregate', 'currency')
            ->all();
    }
}
