<?php

namespace App\Http\Controllers\Checkin;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $employee = Auth::guard('employee')->user()->employee;

        $data = $request->validate([
            'endpoint'         => ['required', 'string'],
            'keys.p256dh'      => ['required', 'string'],
            'keys.auth'        => ['required', 'string'],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'employee_id' => $employee->id,
                'endpoint'    => $data['endpoint'],
                'public_key'  => $data['keys']['p256dh'],
                'auth_token'  => $data['keys']['auth'],
            ]
        );

        return response()->json(['ok' => true]);
    }
}
