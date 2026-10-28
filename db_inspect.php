<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$T = 'c6ad1172-3115-4b7d-9f6d-32560639ca4b';

echo "=== TENANTS ===\n";
foreach (DB::table('tenants')->get() as $r) {
    echo json_encode($r)."\n";
}

echo "\n=== COUNTS PER TABLE / TENANT ===\n";
foreach (['tenant_users', 'sales_reps', 'customers', 'invoices', 'receipts', 'receipt_applications', 'collection_events', 'cash_sessions', 'cash_session_items', 'verification_codes', 'audit_events'] as $t) {
    foreach (DB::table($t)->select('tenant_id', DB::raw('count(*) n'))->groupBy('tenant_id')->get() as $r) {
        echo str_pad($t, 22).$r->tenant_id.'  '.$r->n."\n";
    }
}

echo "\n=== USERS ===\n";
foreach (DB::table('users')->get() as $r) {
    echo json_encode($r)."\n";
}

echo "\n=== TENANT_USERS (LAB_DELTA) ===\n";
foreach (DB::table('tenant_users')->where('tenant_id', $T)->get() as $r) {
    echo json_encode($r)."\n";
}

echo "\n=== SALES_REPS (LAB_DELTA, 10) ===\n";
foreach (DB::table('sales_reps')->where('tenant_id', $T)->limit(10)->get() as $r) {
    echo json_encode($r)."\n";
}

echo "\n=== INVOICE AGG (LAB_DELTA) ===\n";
echo json_encode(DB::table('invoices')->where('tenant_id', $T)
    ->selectRaw('status, cancelled, count(*) n, sum(balance) bal')
    ->groupBy('status', 'cancelled')->get()->toArray())."\n";

echo "\n=== OPEN INVOICES WITH APPLIED RECEIPTS (count) ===\n";
echo DB::table('invoices as i')->where('i.tenant_id', $T)->where('i.cancelled', false)->where('i.balance', '>', 0)
    ->whereExists(function ($q) {
        $q->select(DB::raw(1))->from('receipt_applications as ra')
            ->whereColumn('ra.invoice_id', 'i.id')->whereColumn('ra.tenant_id', 'i.tenant_id');
    })->count()."\n";

echo "\n=== RECEIPT_APPS SAMPLE (LAB_DELTA) ===\n";
foreach (DB::table('receipt_applications')->where('tenant_id', $T)->limit(5)->get() as $r) {
    echo json_encode($r)."\n";
}

echo "\n=== OPEN INVOICE SAMPLE (LAB_DELTA, 5) ===\n";
foreach (DB::table('invoices')->where('tenant_id', $T)->where('cancelled', false)->where('balance', '>', 0)
    ->orderByDesc('balance')->limit(5)->get() as $r) {
    echo json_encode($r)."\n";
}

echo "\n=== CUSTOMERS AGG (LAB_DELTA) ===\n";
echo json_encode(DB::table('customers')->where('tenant_id', $T)
    ->selectRaw('count(*) n, count(sales_rep_id) with_rep, count(collector_user_id) with_coll, count(*) filter (where active) act')
    ->get()->toArray())."\n";

echo "\n=== RECEIPTS SAMPLE (LAB_DELTA, 5) ===\n";
foreach (DB::table('receipts')->where('tenant_id', $T)->limit(5)->get() as $r) {
    echo json_encode($r)."\n";
}

echo "\n=== RECEIPTS TOTAL vs APPLIED (LAB_DELTA) ===\n";
echo json_encode(DB::table('receipts as r')->where('r.tenant_id', $T)
    ->leftJoin('receipt_applications as ra', function ($j) {
        $j->on('ra.receipt_id', '=', 'r.id')->on('ra.tenant_id', '=', 'r.tenant_id');
    })
    ->selectRaw('count(distinct r.id) receipts, count(ra.id) apps, sum(r.total) rtotal, sum(ra.applied_amount) applied')
    ->get()->toArray())."\n";

echo "\n=== SUMMARY (LAB_DELTA) ===\n";
$out = [];
$out['invoice_agg'] = DB::table('invoices')->where('tenant_id', $T)
    ->selectRaw('status, cancelled, count(*) n, sum(balance) bal')->groupBy('status', 'cancelled')->get()->toArray();
$out['open_with_apps'] = DB::table('invoices as i')->where('i.tenant_id', $T)->where('i.cancelled', false)->where('i.balance', '>', 0)
    ->whereExists(function ($q) {
        $q->select(DB::raw(1))->from('receipt_applications as ra')
            ->whereColumn('ra.invoice_id', 'i.id')->whereColumn('ra.tenant_id', 'i.tenant_id');
    })->count();
$out['open_total'] = DB::table('invoices')->where('tenant_id', $T)->where('cancelled', false)->where('balance', '>', 0)->count();
$out['apps_invtype'] = DB::table('receipt_applications')->where('tenant_id', $T)
    ->selectRaw('invtype, count(*) n')->groupBy('invtype')->get()->toArray();
$out['currencies'] = DB::table('invoices')->where('tenant_id', $T)->selectRaw('currency, count(*) n')->groupBy('currency')->get()->toArray();
$out['date_range'] = DB::table('invoices')->where('tenant_id', $T)->selectRaw('min(issue_date) mi, max(issue_date) ma')->get()->toArray();
$out['receipt_dates'] = DB::table('receipts')->where('tenant_id', $T)->selectRaw('min(receipt_date) mi, max(receipt_date) ma')->get()->toArray();
echo json_encode($out, JSON_PRETTY_PRINT)."\n";
echo "\n=== RECEIPTS WITH/WITHOUT APPLICATIONS (LAB_DELTA) ===\n";
$out2 = [];
$out2['receipts_without_apps'] = DB::table('receipts as r')->where('r.tenant_id', $T)
    ->whereNotExists(function ($q) {
        $q->select(DB::raw(1))->from('receipt_applications as ra')
            ->whereColumn('ra.receipt_id', 'r.id')->whereColumn('ra.tenant_id', 'r.tenant_id');
    })->count();
$out2['open_inv_with_apps_rows'] = DB::table('invoices as i')->where('i.tenant_id', $T)->where('i.cancelled', false)->where('i.balance', '>', 0)
    ->whereExists(function ($q) {
        $q->select(DB::raw(1))->from('receipt_applications as ra')
            ->whereColumn('ra.invoice_id', 'i.id')->whereColumn('ra.tenant_id', 'i.tenant_id');
    })
    ->join('receipt_applications as ra', function ($j) {
        $j->on('ra.invoice_id', '=', 'i.id')->on('ra.tenant_id', '=', 'i.tenant_id');
    })->select('i.id as inv', 'i.number', 'i.balance', 'i.currency', 'i.customer_id', 'i.sales_rep_id', 'ra.receipt_id', 'ra.applied_amount', 'ra.invoiceid', 'ra.instid', 'ra.docnum')
    ->limit(5)->get()->toArray();
echo json_encode($out2, JSON_PRETTY_PRINT)."\n";
