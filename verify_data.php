<?php

use App\Models\CollectionEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReceiptApplication;
use App\Models\SalesRep;
use App\Models\Tenant;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// Crear tenant primero - usar código único
$tenant = Tenant::create([
    'name' => 'Laboratorios DELTA S.A.',
    'code' => 'DELTA-TEST',  // Código único para testing
    'active' => true,
    'timezone' => 'America/La_Paz',
    'local_currency' => 'BOB',
]);
$tenantId = $tenant->id;  // Usar el UUID generado por Laravel
echo 'Tenant created: '.$tenant->name.' with ID: '.$tenantId."\n\n";

// Ahora verificar todos los datos
echo "=== TENANT ===\n";
$t = Tenant::find($tenantId);
echo 'ID: '.($t ? $t->id : 'NOT FOUND')."\n";
echo 'Name: '.($t ? $t->name : 'NOT FOUND')."\n\n";

echo "=== SALES REP ===\n";
$reps = SalesRep::where('tenant_id', $tenantId)->get();
foreach ($reps as $r) {
    echo 'ID: '.$r->id.' | Name: '.$r->name.' | SLP: '.$r->slpcode.' | Active: '.($r->active ? 'YES' : 'NO')."\n";
}

echo "=== CUSTOMER ===\n";
$c = Customer::where('tenant_id', $tenantId)->first();
if ($c) {
    echo 'ID: '.$c->id.' | Name: '.$c->name.' | CardCode: '.($c->cardcode ?? 'N/A').' | Active: '.($c->active ? 'YES' : 'NO')."\n";
}

echo "=== INVOICE ===\n";
$inv = Invoice::where('tenant_id', $tenantId)->first();
if ($inv) {
    echo 'ID: '.$inv->id.' | DocEntry: '.$inv->docentry.' | Number: '.$inv->number.' | Status: '.$inv->status.' | Total: '.$inv->total.' | Balance: '.$inv->balance."\n";
}

echo "=== COLLECTION EVENT ===\n";
$events = CollectionEvent::where('tenant_id', $tenantId)->get();
echo 'Count: '.$events->count()."\n";
foreach ($events as $e) {
    echo 'ID: '.$e->id.' | Status: '.$e->status.' | Amount: '.$e->amount.' | Total: '.$e->total.' | SellerUser: '.($e->seller_user_id ?? 'N/A')."\n";
}

echo "=== RECEIPT ===\n";
$rec = Receipt::where('tenant_id', $tenantId)->first();
if ($rec) {
    echo 'ID: '.$rec->id.' | Number: '.$rec->number.' | Paid: '.$rec->paid.' | Balance: '.$rec->balance.' | Cancelled: '.($rec->cancelled ? 'YES' : 'NO')."\n";
}

echo "=== RECEIPT APPLICATION ===\n";
$apps = ReceiptApplication::where('tenant_id', $tenantId)->get();
foreach ($apps as $a) {
    echo 'ReceiptID: '.$a->receipt_id.' | InvoiceID: '.$a->invoice_id.' | InvType: '.$a->invtype.' | Amount: '.$a->amount."\n";
}
