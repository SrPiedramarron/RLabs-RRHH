@extends('checkin.layout')

@section('title', 'Cambiar Contraseña')
@section('header-title', 'Mi Cuenta')
@section('header-subtitle', auth('employee')->user()->employee->nombre_completo_normal ?? '')

@section('content')

<div class="card">
    <div class="card-title">Cambiar Contraseña</div>

    <form method="POST" action="{{ route('checkin.cambiar-clave.submit') }}">
        @csrf

        <div class="form-group">
            <label class="form-label" for="password_actual">Contraseña actual</label>
            <input type="password" id="password_actual" name="password_actual"
                   class="form-input" placeholder="••••••" required>
            @error('password_actual')
            <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password_nueva">Nueva contraseña</label>
            <input type="password" id="password_nueva" name="password_nueva"
                   class="form-input" placeholder="Mínimo 6 caracteres" minlength="6" required>
            @error('password_nueva')
            <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password_nueva_confirmation">Confirmar nueva contraseña</label>
            <input type="password" id="password_nueva_confirmation" name="password_nueva_confirmation"
                   class="form-input" placeholder="Repite la contraseña" required>
        </div>

        <button type="submit" class="btn btn-verde">
            Cambiar contraseña
        </button>
    </form>
</div>

{{-- Notificaciones push --}}
<div class="card" style="margin-top:8px;">
    <div class="card-title">Notificaciones</div>
    <p style="font-size:13px; color:var(--gris); margin-bottom:12px;">
        Activa los avisos para enterarte apenas RRHH apruebe o rechace tus solicitudes.
    </p>
    <button type="button" id="btn-activar-push" class="btn btn-azul">
        Activar notificaciones
    </button>
    <div id="push-status" style="font-size:12px; color:var(--gris); margin-top:8px; text-align:center;"></div>
</div>

@push('scripts')
<script>
(function () {
    const VAPID_PUBLIC_KEY = @json(config('services.vapid.public_key'));
    const btn = document.getElementById('btn-activar-push');
    const status = document.getElementById('push-status');

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        return Uint8Array.from([...rawData].map(c => c.charCodeAt(0)));
    }

    async function actualizarEstado() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            status.textContent = 'Tu navegador no soporta notificaciones push.';
            btn.disabled = true;
            return;
        }
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        if (sub) {
            btn.textContent = 'Notificaciones activadas ✓';
            btn.disabled = true;
            status.textContent = '';
        }
    }

    btn?.addEventListener('click', async function () {
        if (!VAPID_PUBLIC_KEY) {
            status.textContent = 'Notificaciones no disponibles por ahora.';
            return;
        }

        btn.disabled = true;
        status.textContent = 'Activando...';

        try {
            const permiso = await Notification.requestPermission();
            if (permiso !== 'granted') {
                status.textContent = 'Permiso denegado. Actívalo desde la configuración del navegador.';
                btn.disabled = false;
                return;
            }

            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
            });

            await fetch('{{ route("checkin.push.subscribe") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(sub),
            });

            btn.textContent = 'Notificaciones activadas ✓';
            status.textContent = '';
        } catch (e) {
            status.textContent = 'No se pudo activar. Intenta de nuevo.';
            btn.disabled = false;
        }
    });

    actualizarEstado();
})();
</script>
@endpush

{{-- Cerrar sesión --}}
<div class="card" style="margin-top:8px;">
    <div class="card-title">Sesión</div>
    <form method="POST" action="{{ route('checkin.logout') }}">
        @csrf
        <button type="submit" class="btn btn-gris">
            Cerrar sesión
        </button>
    </form>
</div>

@endsection
