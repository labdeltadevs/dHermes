<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$tables = ['tenants', 'users', 'tenant_users', 'sales_reps', 'customers', 'invoices', 'collection_events', 'receipts', 'receipt_applications', 'cash_sessions', 'cash_session_items', 'verification_codes', 'audit_events'];
foreach ($tables as $t) {
    if (! Schema::hasTable($t)) {
        echo "$t: MISSING\n";

        continue;
    }
    $total = DB::table($t)->count();
    echo "=== $t (total $total) ===\n";
    if (Schema::hasColumn($t, 'tenant_id')) {
        foreach (DB::table($t)->selectRaw('tenant_id, count(*) as c')->groupBy('tenant_id')->get() as $row) {
            echo '  tenant '.$row->tenant_id.' => '.$row->c."\n";
        }
    }
}
echo "\n--- invoices con saldo + receipt_application (tenant c6ad1172) ---\n";
$rows = DB::table('invoices as i')
    ->join('receipt_applications as ra', function ($j) {
        $j->on('ra.invoice_id', '=', 'i.id')->on('ra.tenant_id', '=', 'i.tenant_id');
    })
    ->join('receipts as r', function ($j) {
        $j->on('r.id', '=', 'ra.receipt_id')->on('r.tenant_id', '=', 'ra.tenant_id');
    })
    ->where('i.tenant_id', 'c6ad1172-3115-4b7d-9f6d-32560639ca4b')
    ->where('i.balance', '>', 0)
    ->where('i.cancelled', false)
    ->where('r.cancelled', false)
    ->select('i.id as invoice_id', 'i.number as invoice_number', 'i.docentry as invoice_docentry', 'i.total as invoice_total', 'i.paid as invoice_paid', 'i.balance as invoice_balance', 'i.currency as invoice_currency', 'i.issue_date', 'i.customer_id', 'r.id as receipt_id', 'r.number as receipt_number', 'r.docentry as receipt_docentry', 'r.total as receipt_total', 'r.currency as receipt_currency', 'r.receipt_date', 'ra.id as ra_id', 'ra.applied_amount', 'ra.invtype', 'ra.docnum as ra_docnum', 'ra.invoiceid as ra_invoiceid', 'ra.docentry as ra_docentry', 'ra.instid as ra_instid')
    ->limit(8)
    ->get();
foreach ($rows as $r) {
    echo '  '.json_encode($r, JSON_UNESCAPED_UNICODE)."\n";
}

echo "\n--- tenant_users schema ---\n";
foreach (Schema::getColumnListing('tenant_users') as $c) {
    echo "  $c\n";
}
echo "\n--- users schema ---\n";
foreach (Schema::getColumnListing('users') as $c) {
    echo "  $c\n";
}
echo "\n--- collection_events schema ---\n";
foreach (Schema::getColumnListing('collection_events') as $c) {
    echo "  $c\n";
}
