<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\CollectionEvent;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\SalesRep;
use App\Models\Tenant;
use Illuminate\Contracts\Console\Kernel;

$tenantId = 'c69269b9-3332-4559-b62f-f72ce77f7d3a';

echo "========================================\n";
echo "INVESTIGACIÓN DE DATOS PARA TENANT\n";
echo 'ID: '.$tenantId."\n";
echo "========================================\n\n";

// 1. Tenant details
echo "--- 1. Tenant ---\n";
$tenant = Tenant::find($tenantId);
if ($tenant) {
    echo 'Found: ID='.$tenant->id.' Code='.$tenant->code.' Name='.$tenant->name."\n";
    echo "Exists in DB: Yes\n";
} else {
    echo "Not found!\n";
}

// 2. Sales Reps for this tenant
echo "\n--- 2. Sales Reps for this tenant ---\n";
$reps = SalesRep::where('tenant_id', $tenantId)->get();
echo 'Count: '.$reps->count()."\n";
foreach ($reps as $r) {
    echo '  ID: '.$r->id.' | Name: '.$r->name.' | SLP Code: '.($r->slpcode ?? 'N/A').' | Active: '.($r->active ? 'YES' : 'NO')."\n";
    // Check his customers
    $customers = $r->customers()->get();
    echo '    Customers: '.$customers->count()."\n";
    foreach ($customers as $c) {
        echo '      - Customer: '.$c->name.' | Code: '.($c->code ?? 'N/A').' | Status: '.($c->active ? 'ACTIVE' : 'INACTIVE')."\n";
    }
}

// 3. Collection Events for this tenant
echo "\n--- 3. Collection Events for this tenant ---\n";
$events = CollectionEvent::where('tenant_id', $tenantId)->get();
echo 'Count: '.$events->count()."\n";
foreach ($events as $e) {
    echo '  ID: '.$e->id.' | Status: '.$e->status.' | InvoiceID: '.($e->invoice_id ?? 'N/A').' | Currency: '.$e->currency.' | CustomerID: '.($e->customer_id ?? 'N/A')."\n";
}

// 4. Invoices for this tenant
echo "\n--- 4. Invoices for this tenant ---\n";
$invoices = Invoice::where('tenant_id', $tenantId)->get();
echo 'Count: '.$invoices->count()."\n";
foreach ($invoices as $inv) {
    echo '  ID: '.$inv->id.' | DocNum: '.($inv->docnum ?? 'N/A').' | CustomerID: '.$inv->customer_id.' | Currency: '.$inv->currency.' | Status: '.($inv->status ?? 'N/A')."\n";
}

// 5. Receipts for this tenant
echo "\n--- 5. Receipts for this tenant ---\n";
$receipts = Receipt::where('tenant_id', $tenantId)->get();
echo 'Count: '.$receipts->count()."\n";
foreach ($receipts as $r) {
    echo '  ID: '.$r->id.' | Number: '.($r->number ?? 'N/A').' | CustomerID: '.$r->customer_id.' | Currency: '.$r->currency.' | Cancelled: '.($r->cancelled ? 'YES' : 'NO')."\n";
}

// 6. ALL Sales Reps (any tenant) - to see if there are any at all
echo "\n--- 6. ALL Sales Reps (any tenant) ---\n";
$allReps = SalesRep::all();
echo 'Total: '.$allReps->count()."\n";
foreach ($allReps as $r) {
    $t = $r->tenant;
    echo '  Rep: '.$r->name.' | Tenant: '.($t ? $t->name.' ('.$t->code.')' : 'NULL').' | Active: '.($r->active ? 'YES' : 'NO')."\n";
}

// 7. ALL Collection Events (any tenant)
echo "\n--- 7. ALL Collection Events (any tenant) ---\n";
$allEvents = CollectionEvent::all();
echo 'Total: '.$allEvents->count()."\n";
foreach ($allEvents as $e) {
    $t = $e->tenant;
    echo '  Event: '.$e->id.' | Tenant: '.($t ? $t->name.' ('.$t->code.')' : 'NULL').' | Status: '.$e->status."\n";
}

// 8. ALL Invoices (any tenant)
echo "\n--- 8. ALL Invoices (any tenant) ---\n";
$allInv = Invoice::all();
echo 'Total: '.$allInv->count()."\n";
foreach ($allInv as $i) {
    $t = $i->tenant;
    echo '  Invoice: '.$i->id.' | Tenant: '.($t ? $t->name.' ('.$t->code.')' : 'NULL').' | Customer: '.$i->customer_id.' | DocNum: '.($i->docnum ?? 'N/A')."\n";
}

// 9. ALL Receipts (any tenant)
echo "\n--- 9. ALL Receipts (any tenant) ---\n";
$allRec = Receipt::all();
echo 'Total: '.$allRec->count()."\n";
foreach ($allRec as $r) {
    $t = $r->tenant;
    echo '  Receipt: '.$r->id.' | Tenant: '.($t ? $t->name.' ('.$t->code.')' : 'NULL').' | Customer: '.$r->customer_id.' | Currency: '.$r->currency.' | Cancelled: '.($r->cancelled ? 'YES' : 'NO')."\n";
}

echo "\n========================================\n";
echo "FIN DE INVESTIGACIÓN\n";
echo "========================================\n";
