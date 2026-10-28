<?php

namespace Database\Seeders;

use App\Actions\Cash\AddCashSessionItem;
use App\Actions\Cash\ApproveCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Cash\SubmitCashSession;
use App\Actions\Collection\AcceptCollection;
use App\Actions\Collection\RegisterCollection;
use App\Enums\CashSessionStatus;
use App\Enums\CollectionKind;
use App\Enums\CollectionStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TenantRole;
use App\Models\CashSession;
use App\Models\CashSessionItem;
use App\Models\CollectionEvent;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Verification\IssueVerificationCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Demo data for the LAB_DELTA tenant.
 *
 * The seeder is idempotent and non-destructive: every record is resolved
 * through a tenant-scoped natural key before it is created, so running it
 * twice leaves the database in the same state. It never truncates tables and
 * never modifies records it did not create.
 */
class TenantDataSeed extends Seeder
{
    private const TENANT_CODE = 'LAB_DELTA';

    private const ADMIN_EMAIL = 'demo.admin@lab-delta.test';

    private const SELLER_EMAIL = 'demo.seller@lab-delta.test';

    private const DEMO_PASSWORD = 'demo-password-1234';

    /**
     * Tenant-scoped natural keys.
     */
    private const COLLECTION_VOUCHER = 'DEMO-COL-0001';

    private const CASH_ITEM_REFERENCE = 'DEMO-CASH-ITEM-0001';

    private const EPSILON = 0.000001;

    public function run(): void
    {
        DB::transaction(function (): void {
            $tenant = $this->resolveTenant();

            app(CurrentTenant::class)->set($tenant->id);

            $admin = $this->ensureUser(self::ADMIN_EMAIL, 'Demo Administrador LAB_DELTA');
            $seller = $this->ensureUser(self::SELLER_EMAIL, 'Demo Vendedor LAB_DELTA');

            $this->ensureMembership($admin, $tenant, TenantRole::Admin, null);

            $invoice = $this->resolveDemoInvoice();

            $this->ensureMembership($seller, $tenant, TenantRole::Seller, $invoice->sales_rep_id);

            Auth::setUser($seller);

            $collection = $this->ensureCollection($seller, $invoice);

            $cashSession = $this->ensureCashSessionItem($seller, $collection);

            if ($cashSession === null) {
                return;
            }

            Auth::setUser($admin);

            $this->ensureCollectionAccepted($admin, $collection);

            Auth::setUser($seller);

            $cashSession = $this->ensureCashSessionSubmitted($seller, $cashSession);

            Auth::setUser($admin);

            $this->ensureCashSessionApproved($admin, $cashSession);

            $this->ensureVerificationCodes($admin, $collection, $cashSession);
        });
    }

    private function resolveTenant(): Tenant
    {
        $tenant = Tenant::query()->where('code', self::TENANT_CODE)->first();

        if ($tenant === null) {
            throw new RuntimeException(sprintf('No existe el tenant "%s".', self::TENANT_CODE));
        }

        return $tenant;
    }

    private function ensureUser(string $email, string $name): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            return $user;
        }

        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password_hash' => self::DEMO_PASSWORD,
            'active' => true,
        ]);
    }

    /**
     * Create the membership when it is missing and align the role and the
     * sales rep otherwise.
     */
    private function ensureMembership(User $user, Tenant $tenant, TenantRole $role, ?string $salesRepId): TenantUser
    {
        $membership = TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        if ($membership === null) {
            return TenantUser::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => $role->value,
                'sales_rep_id' => $salesRepId,
                'active' => true,
            ]);
        }

        if ($membership->role !== $role || $membership->sales_rep_id !== $salesRepId) {
            $membership->update([
                'role' => $role->value,
                'sales_rep_id' => $salesRepId,
                'active' => true,
            ]);
        }

        return $membership;
    }

    /**
     * Resolve the demo invoice: an open invoice of this tenant with a receipt
     * applied to it through an RCT2 application (InvType = 13), where the
     * receipt shares the customer and the currency of the invoice.
     *
     * When the demo collection already exists, its own invoice wins so the
     * seeder always keeps working on the same document.
     */
    private function resolveDemoInvoice(): Invoice
    {
        $existing = CollectionEvent::query()
            ->where('voucher_code', self::COLLECTION_VOUCHER)
            ->first();

        $invoice = $existing?->invoice;

        if ($invoice !== null) {
            return $invoice;
        }

        $invoiceIds = DB::table('receipt_applications as applications')
            ->join('receipts as receipts', 'receipts.id', '=', 'applications.receipt_id')
            ->join('invoices as invoices', 'invoices.id', '=', 'applications.invoice_id')
            ->where('applications.tenant_id', app(CurrentTenant::class)->id())
            ->where('applications.invtype', '13')
            ->where('receipts.cancelled', false)
            ->where('invoices.cancelled', false)
            ->where('invoices.status', InvoiceStatus::Open->value)
            ->where('invoices.balance', '>', 0)
            ->whereNotNull('invoices.sales_rep_id')
            ->whereColumn('receipts.customer_id', 'invoices.customer_id')
            ->whereColumn('receipts.currency', 'invoices.currency')
            ->where('applications.applied_amount', '>', 0)
            ->orderBy('invoices.id')
            ->limit(50)
            ->pluck('invoices.id');

        foreach ($invoiceIds as $invoiceId) {
            $candidate = Invoice::query()->whereKey($invoiceId)->first();

            if ($candidate !== null) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'No se encontro una factura abierta de LAB_DELTA con un comprobante aplicado.'
        );
    }

    /**
     * Resolve the demo collection through its tenant-scoped voucher code,
     * registering it only when it does not exist yet.
     */
    private function ensureCollection(User $seller, Invoice $invoice): CollectionEvent
    {
        $existing = CollectionEvent::query()
            ->where('voucher_code', self::COLLECTION_VOUCHER)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        [$receipt, $amount, $localAmount] = $this->resolveInvoiceApplication($invoice);

        $balance = (float) $invoice->balance;

        $kind = abs($amount - $balance) <= self::EPSILON
            ? CollectionKind::Full
            : CollectionKind::Partial;

        return app(RegisterCollection::class)->handle($seller, $invoice, [
            'amount' => $amount,
            'currency' => $invoice->currency,
            'local_amount' => $localAmount,
            'payment_method' => PaymentMethod::Cash,
            'collected_on' => $receipt->receipt_date->toDateString(),
            'kind' => $kind,
            'voucher_code' => self::COLLECTION_VOUCHER,
            'notes' => 'Cobranza de demostracion LAB_DELTA.',
        ]);
    }

    /**
     * Find the receipt applied to the invoice through an RCT2 application
     * (InvType = 13) and the amount that can be collected from it.
     *
     * @return array{0: Receipt, 1: float, 2: float|null}
     */
    private function resolveInvoiceApplication(Invoice $invoice): array
    {
        $applications = DB::table('receipt_applications')
            ->where('tenant_id', $invoice->tenant_id)
            ->where('invoice_id', $invoice->id)
            ->where('invtype', '13')
            ->where('applied_amount', '>', 0)
            ->orderBy('applied_amount')
            ->get(['receipt_id', 'applied_amount', 'local_applied_amount']);

        foreach ($applications as $application) {
            $receipt = Receipt::query()->whereKey($application->receipt_id)->first();

            if ($receipt === null || $receipt->cancelled) {
                continue;
            }

            if ($receipt->customer_id !== $invoice->customer_id) {
                continue;
            }

            if ($receipt->currency !== $invoice->currency) {
                continue;
            }

            $amount = min((float) $application->applied_amount, (float) $invoice->balance);

            if ($amount <= 0) {
                continue;
            }

            $localAmount = null;

            if ($application->local_applied_amount !== null && $invoice->local_balance !== null) {
                $localAmount = min((float) $application->local_applied_amount, (float) $invoice->local_balance);
            }

            return [$receipt, $amount, $localAmount];
        }

        throw new RuntimeException('La factura de demostracion no tiene un comprobante aplicado.');
    }

    /**
     * Ensure a cash session exists for the seller and already contains the
     * demo collection as an item.
     *
     * The session is only returned when it can still receive the item, so a
     * closed session from a previous run never blocks the seeder.
     */
    private function ensureCashSessionItem(User $seller, CollectionEvent $collection): ?CashSession
    {
        $item = CashSessionItem::query()
            ->where('reference', self::CASH_ITEM_REFERENCE)
            ->first();

        if ($item !== null) {
            return $item->cashSession;
        }

        $existing = CashSessionItem::query()
            ->where('collection_event_id', $collection->id)
            ->first();

        if ($existing !== null) {
            return $existing->cashSession;
        }

        $cashSession = CashSession::query()
            ->where('seller_user_id', $seller->id)
            ->where('currency', $collection->currency)
            ->where('status', CashSessionStatus::Open)
            ->first();

        if ($cashSession === null) {
            $cashSession = app(OpenCashSession::class)->handle($seller, [
                'currency' => $collection->currency,
                'notes' => 'Arqueo de demostracion LAB_DELTA.',
            ]);
        }

        app(AddCashSessionItem::class)->handle($seller, $cashSession, [
            'collection_event_id' => $collection->id,
            'payment_method' => $collection->payment_method,
            'amount' => (float) $collection->amount,
            'reference' => self::CASH_ITEM_REFERENCE,
        ]);

        return $cashSession->refresh();
    }

    /**
     * Accept the demo collection, recording the DocNum registered manually in the ERP.
     */
    private function ensureCollectionAccepted(User $admin, CollectionEvent $collection): void
    {
        if ($collection->status !== CollectionStatus::Pending) {
            return;
        }

        app(AcceptCollection::class)->handle($admin, $collection, '900000001', true);
    }

    private function ensureCashSessionSubmitted(User $seller, CashSession $cashSession): CashSession
    {
        if ($cashSession->status !== CashSessionStatus::Open) {
            return $cashSession;
        }

        $expected = (float) $cashSession->items()->sum('amount');

        return app(SubmitCashSession::class)->handle($seller, $cashSession, [
            'counted_amount' => $expected,
            'notes' => 'Cierre de demostracion LAB_DELTA.',
        ]);
    }

    private function ensureCashSessionApproved(User $admin, CashSession $cashSession): void
    {
        if ($cashSession->status !== CashSessionStatus::Submitted) {
            return;
        }

        app(ApproveCashSession::class)->handle($admin, $cashSession);
    }

    /**
     * Issue the verification codes of the demo collection and cash session.
     */
    private function ensureVerificationCodes(User $admin, CollectionEvent $collection, CashSession $cashSession): void
    {
        $issuer = app(IssueVerificationCode::class);

        $issuer->handle($admin, $collection);
        $issuer->handle($admin, $cashSession);
    }
}
