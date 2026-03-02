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

        // Iniciar sesión con el guard 'employee'
        Auth::guard('employee')->login($credential, $request->boolean('remember'));

        // Registrar último acceso
        $credential->update(['ultimo_acceso' => now()]);

        // Si la contraseña es el DNI, forzar cambio
        if (Hash::check($employee->dni, $credential->password)) {
            return redirect()->route('checkin.cambiar-clave')
                ->with('warning', 'Por seguridad, debes cambiar tu contraseña antes de continuar.');
        }

        return redirect()->route('checkin.home');
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
