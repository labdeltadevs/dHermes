<?php

namespace App\Http\Controllers;

use App\Support\Verification\VerificationResult;
use App\Support\Verification\VerifyCode;
use Illuminate\View\View;

final class VerificationController extends Controller
{
    public function __invoke(string $code, VerifyCode $verifyCode): View
    {
        $result = $verifyCode->handle($code);

        abort_if($result === VerificationResult::NotFound, 404);

        return view('verification.show', [
            'result' => $result,
            'code' => $code,
        ]);
    }
}
