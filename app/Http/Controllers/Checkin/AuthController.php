<?php

namespace App\Http\Controllers\Checkin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // ── Login ─────────────────────────────────────────────────────────────────

    public function showLogin()
    {
        if (Auth::guard('employee')->check()) {
            return redirect()->route('checkin.home');
        }
        return view('checkin.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'dni'      => ['required', 'digits:8'],
            'password' => ['required', 'min:4'],
        ], [
            'dni.required'      => 'Ingresa tu DNI.',
            'dni.digits'        => 'El DNI debe tener 8 dígitos.',
            'password.required' => 'Ingresa tu contraseña.',
        ]);

        // Buscar empleado activo por DNI
        $employee = Employee::where('dni', $request->dni)
            ->where('active', true)
            ->first();

        if (! $employee) {
            return back()->withErrors(['dni' => 'DNI no encontrado o empleado inactivo.']);
        }

        // Buscar credencial activa
        $credential = EmployeeCredential::where('employee_id', $employee->id)
            ->where('active', true)
            ->first();

        if (! $credential) {
            return back()->withErrors(['dni' => 'No tienes acceso a la app. Contacta a RR.HH.']);
        }

        // Verificar contraseña
        if (! Hash::check($request->password, $credential->password)) {
            return back()->withErrors(['password' => 'Contraseña incorrecta.']);
        }

        // Iniciar sesión con el guard 'employee'. Siempre con "recuérdame"
        // (cookie de larga duración, ~5 años): es una app de marcado diario
        // desde el celular, no tiene sentido pedir login de nuevo cada vez
        // que la sesión de 24h expira por estar la pantalla bloqueada.
        Auth::guard('employee')->login($credential, true);

        // Registrar último acceso
        $credential->update(['ultimo_acceso' => now()]);

        // Si la contraseña es el DNI, forzar cambio
        if (Hash::check($employee->dni, $credential->password)) {
            return redirect()->route('checkin.cambiar-clave')
                ->with('warning', 'Por seguridad, debes cambiar tu contraseña antes de continuar.');
        }

        return redirect()->route('checkin.home');
    }

    // ── Recuperar contraseña ──────────────────────────────────────────────────

    public function showOlvide()
    {
        return view('checkin.olvide-clave');
    }

    public function enviarCodigo(Request $request)
    {
        $request->validate(['dni' => ['required', 'digits:8']], [
            'dni.required' => 'Ingresa tu DNI.',
            'dni.digits'   => 'El DNI debe tener 8 dígitos.',
        ]);

        $employee = Employee::where('dni', $request->dni)->where('active', true)->first();
        $credential = $employee
            ? EmployeeCredential::where('employee_id', $employee->id)->where('active', true)->first()
            : null;

        // La respuesta es siempre la misma: no revela si el DNI existe ni si tiene correo.
        if ($employee && $credential && filter_var($employee->email, FILTER_VALIDATE_EMAIL)) {
            $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $credential->forceFill([
                'reset_code'       => Hash::make($codigo),
                'reset_expires_at' => now()->addMinutes(15),
                'reset_intentos'   => 0,
            ])->save();

            try {
                \Illuminate\Support\Facades\Mail::to($employee->email)
                    ->send(new \App\Mail\CodigoRecuperacionMail($employee->nombres, $codigo));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('checkin.restablecer')->with('reset_dni', $request->dni);
    }

    public function showRestablecer()
    {
        return view('checkin.restablecer-clave');
    }

    public function restablecer(Request $request)
    {
        $dni = $request->input('dni');

        $request->validate([
            'codigo'         => ['required', 'digits:6'],
            'password_nueva' => ['required', 'min:6', 'confirmed'],
        ], [
            'codigo.digits'            => 'El código tiene 6 dígitos.',
            'password_nueva.min'       => 'La nueva contraseña debe tener al menos 6 caracteres.',
            'password_nueva.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $employee = $dni ? Employee::where('dni', $dni)->where('active', true)->first() : null;
        $credential = $employee
            ? EmployeeCredential::where('employee_id', $employee->id)->where('active', true)->first()
            : null;

        $invalido = ! $credential || ! $credential->reset_code || ! $credential->reset_expires_at
            || $credential->reset_expires_at->isPast() || $credential->reset_intentos >= 5;

        if (! $invalido && ! Hash::check($request->codigo, $credential->reset_code)) {
            $credential->increment('reset_intentos');
            $invalido = true;
        }

        if ($invalido) {
            return back()->withInput($request->only('dni'))->with('reset_dni', $dni)
                ->withErrors(['codigo' => 'El código no es válido o ya venció. Pide uno nuevo.']);
        }

        $credential->forceFill([
            'password' => Hash::make($request->password_nueva),
            'reset_code' => null, 'reset_expires_at' => null, 'reset_intentos' => 0,
        ])->save();

        return redirect()->route('checkin.login')->with('success', '✅ Contraseña actualizada. Ya puedes ingresar.');
    }

    public function logout(Request $request)
    {
        Auth::guard('employee')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('checkin.login');
    }

    // ── Cambiar contraseña ────────────────────────────────────────────────────

    public function showCambiarClave()
    {
        return view('checkin.cambiar-clave');
    }

    public function cambiarClave(Request $request)
    {
        $request->validate([
            'password_actual' => ['required'],
            'password_nueva'  => ['required', 'min:6', 'confirmed'],
        ], [
            'password_nueva.min'       => 'La nueva contraseña debe tener al menos 6 caracteres.',
            'password_nueva.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $credential = Auth::guard('employee')->user();

        if (! Hash::check($request->password_actual, $credential->password)) {
            return back()->withErrors(['password_actual' => 'La contraseña actual es incorrecta.']);
        }

        $credential->update(['password' => Hash::make($request->password_nueva)]);

        return redirect()->route('checkin.home')
            ->with('success', '✅ Contraseña actualizada correctamente.');
    }
}
