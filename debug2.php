<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\CollectionEvent;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Tenant;
use Illuminate\Contracts\Console\Kernel;

$tenantId = 'c69269b9-3332-4559-b62f-f72ce77f7d3a';

echo "=== ALL TENANTS ===\n";
foreach (Tenant::all() as $t) {
    echo 'ID: '.$t->id.' | CODE: '.$t->code.' | NAME: '.$t->name."\n";
}

echo "\n=== ALL COLLECTION EVENTS ===\n";
$allCE = CollectionEvent::all();
echo 'Total: '.$allCE->count()."\n";
foreach ($allCE as $e) {
    echo '  id: '.$e->id.' | tenant_id: '.$e->tenant_id.' | invoice_id: '.($e->invoice_id ?? 'NULL').' | currency: '.$e->currency.' | status: '.$e->status."\n";
}

echo "\n=== ALL INVOICES ===\n";
$allInv = Invoice::all();
echo 'Total: '.$allInv->count()."\n";
foreach ($allInv as $e) {
    echo '  id: '.$e->id.' | tenant_id: '.$e->tenant_id.' | customer_id: '.$e->customer_id.' | currency: '.$e->currency.' | docnum: '.($e->docnum ?? 'NULL')."\n";
}

echo "\n=== ALL RECEIPTS for tenant ".$tenantId." ===\n";
$allRec = Receipt::where('tenant_id', $tenantId)->get();
echo 'Total: '.$allRec->count()."\n";
foreach ($allRec as $e) {
    echo '  id: '.$e->id.' | number: '.($e->number ?? 'NULL').' | customer_id: '.$e->customer_id.' | currency: '.$e->currency.' | cancelled: '.($e->cancelled ? 'YES' : 'NO')."\n";
}
