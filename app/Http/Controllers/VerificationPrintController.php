<?php

namespace App\Http\Controllers;

use App\Models\CollectionEvent;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Verification\QrCode;
use App\Support\Verification\VerificationResult;
use App\Support\Verification\VerifyCode;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class VerificationPrintController extends Controller
{
    public function __invoke(string $code, VerifyCode $verifyCode): View
    {
        $result = $verifyCode->handle($code);

        abort_if($result === VerificationResult::NotFound, 404);

        $verificationCode = $verifyCode->find($code);

        abort_if($verificationCode === null, 404);
        abort_unless(auth()->check(), 403);

        app(CurrentTenant::class)->set($verificationCode->tenant_id);

        Gate::authorize('view', $verificationCode);

        $subject = $verificationCode->verifiable;

        if ($subject instanceof CollectionEvent) {
            $subject->loadMissing(['invoice.customer']);
        }

        $publicUrl = route('verification.show', $code);

        return view('verification.print', [
            'result' => $result,
            'code' => $code,
            'subject' => $subject,
            'tenant' => $verificationCode->tenant,
            'publicUrl' => $publicUrl,
            'qrSvg' => app(QrCode::class)->svg($publicUrl),
        ]);
    }
}
