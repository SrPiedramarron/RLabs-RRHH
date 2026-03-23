@extends('checkin.layout')

@section('title', 'Marcar Asistencia')
@section('header-title', 'Marcar Asistencia')
@section('header-subtitle', now()->locale('es')->isoFormat('dddd D [de] MMMM'))

@section('header-right')
<form method="POST" action="{{ route('checkin.logout') }}" style="margin:0;">
    @csrf
    <button type="submit" style="background:none; border:none; color:rgba(255,255,255,0.8); cursor:pointer; font-size:12px;">
        Salir
    </button>
</form>
@endsection

@push('styles')
<style>
    #camara-preview {
        width: 100%;
        border-radius: 10px;
        background: #000;
        display: block;
        max-height: 260px;
        object-fit: cover;
    }
    #foto-tomada {
        width: 100%;
        border-radius: 10px;
        display: none;
        max-height: 260px;
        object-fit: cover;
    }
    .estado-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid var(--borde);
        font-size: 14px;
    }
    .estado-item:last-child { border-bottom: none; }
    .estado-hora { font-weight: 700; font-size: 16px; color: var(--verde); margin-left: auto; }
    .gps-status { font-size: 12px; color: var(--gris); margin-top: 6px; }
</style>
@endpush

@section('content')

{{-- Info del empleado --}}
<div class="card" style="display:flex; align-items:center; gap:14px; padding:16px 20px;">
    <div style="width:48px; height:48px; border-radius:50%; background:var(--verde-light);
                display:flex; align-items:center; justify-content:center; flex-shrink:0;">
        @if($employee->foto_perfil)
            <img src="{{ Storage::url($employee->foto_perfil) }}"
                 style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
        @else
            <svg width="24" height="24" fill="none" stroke="var(--verde)" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
        @endif
    </div>
    <div>
        <div style="font-weight:700; font-size:15px;">{{ $employee->nombre_completo_normal }}</div>
        <div style="font-size:12px; color:var(--gris);">{{ $employee->cargo ?? $employee->location->nombre }}</div>
        @if($employee->schedule)
        <div style="font-size:11px; color:var(--verde); margin-top:2px;">
            🕐 {{ $employee->schedule->nombre }} · {{ substr($employee->schedule->hora_entrada,0,5) }}–{{ substr($employee->schedule->hora_salida,0,5) }}
        </div>
        @endif
    </div>
</div>

{{-- Estado de hoy --}}
@if($tieneEntrada || $tieneSalida)
<div class="card">
    <div class="card-title">Marcaciones de hoy</div>
    @foreach($checkinHoy as $c)
    <div class="estado-item">
        <span class="badge {{ $c->tipo === 'entrada' ? 'badge-verde' : 'badge-rojo' }}">
            {{ strtoupper($c->tipo) }}
        </span>
        <span style="font-size:13px; color:var(--gris);">
            @if($c->estado_facial === 'aprobado') ✅ Facial OK
            @elseif($c->estado_facial === 'sin_perfil') ⚠️ Sin foto perfil
            @elseif($c->estado_facial === 'rechazado') ❌ Facial rechazado
            @else ⏳ Pendiente
            @endif
        </span>
        <span class="estado-hora">{{ $c->fecha_hora->format('H:i') }}</span>
    </div>
    @endforeach
</div>
@endif

{{-- Formulario de marcado --}}
@if(!$tieneSalida)
<div class="card">
    <div class="card-title">
        {{ !$tieneEntrada ? 'Registrar Entrada' : 'Registrar Salida' }}
    </div>

    {{-- Vista previa cámara --}}
    <div style="margin-bottom:14px; position:relative;">
        <video id="camara-preview" autoplay playsinline muted></video>
        <img id="foto-tomada" alt="Foto tomada">
        <div style="position:absolute; bottom:8px; right:8px;">
            <button type="button" id="btn-retomar"
                style="display:none; background:rgba(0,0,0,0.6); color:white;
                       border:none; border-radius:8px; padding:6px 12px; font-size:12px; cursor:pointer;">
                📷 Retomar
            </button>
        </div>
    </div>

    <div class="gps-status" id="gps-status">📍 Obteniendo ubicación...</div>

    <form id="form-marcar" method="POST" action="{{ route('checkin.marcar') }}" style="margin-top:14px;">
        @csrf
        <input type="hidden" name="tipo"     value="{{ !$tieneEntrada ? 'entrada' : 'salida' }}">
        <input type="hidden" name="latitud"  id="input-latitud">
        <input type="hidden" name="longitud" id="input-longitud">
        <input type="hidden" name="precision" id="input-precision">
        <input type="hidden" name="foto"     id="input-foto">

        <button type="button" id="btn-capturar" class="btn btn-outline" style="margin-bottom:10px;" disabled>
            📷 Tomar foto
        </button>

        <button type="submit" id="btn-marcar"
            class="btn {{ !$tieneEntrada ? 'btn-verde' : 'btn-rojo' }}"
            disabled>
            <span class="btn-text">
                {{ !$tieneEntrada ? '🟢 Registrar Entrada' : '🔴 Registrar Salida' }}
            </span>
            <div class="spinner"></div>
        </button>
    </form>
</div>
@else
<div class="card" style="text-align:center; padding:30px 20px;">
    <div style="font-size:48px; margin-bottom:12px;">✅</div>
    <div style="font-size:16px; font-weight:700; color:var(--verde);">Jornada completada</div>
    <div style="font-size:13px; color:var(--gris); margin-top:6px;">Ya registraste entrada y salida hoy.</div>
</div>
@endif

@endsection

@push('scripts')
<script>
(function() {
    const video      = document.getElementById('camara-preview');
    const fotoImg    = document.getElementById('foto-tomada');
    const btnCaptura = document.getElementById('btn-capturar');
    const btnRetomar = document.getElementById('btn-retomar');
    const btnMarcar  = document.getElementById('btn-marcar');
    const inputFoto  = document.getElementById('input-foto');
    const inputLat   = document.getElementById('input-latitud');
    const inputLng   = document.getElementById('input-longitud');
    const inputPrec  = document.getElementById('input-precision');
    const gpsStatus  = document.getElementById('gps-status');
    const form       = document.getElementById('form-marcar');

    if (!video) return; // Jornada completada, no hay formulario

    let stream      = null;
    let gpsOk       = false;
    let fotoTomada  = false;

    // ── 1. Iniciar cámara ──────────────────────────────────────────────────
    async function iniciarCamara() {
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false
            });
            video.srcObject = stream;
            btnCaptura.disabled = false;
        } catch(e) {
            alert('No se pudo acceder a la cámara. Por favor, permite el acceso e intenta nuevamente.');
        }
    }

    // ── 2. Obtener GPS ─────────────────────────────────────────────────────
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                inputLat.value  = pos.coords.latitude;
                inputLng.value  = pos.coords.longitude;
                inputPrec.value = pos.coords.accuracy;
                gpsOk = true;
                gpsStatus.innerHTML = '📍 Ubicación obtenida (' + Math.round(pos.coords.accuracy) + 'm de precisión)';
                gpsStatus.style.color = 'var(--verde)';
                verificarListo();
            },
            function() {
                gpsStatus.innerHTML = '⚠️ No se pudo obtener ubicación GPS';
                gpsStatus.style.color = 'var(--amarillo)';
                // Permitir marcar igual, pero sin GPS
                gpsOk = true;
                verificarListo();
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    // ── 3. Tomar foto ──────────────────────────────────────────────────────
    btnCaptura && btnCaptura.addEventListener('click', function() {
        const canvas = document.createElement('canvas');
        canvas.width  = video.videoWidth  || 640;
        canvas.height = video.videoHeight || 480;
        canvas.getContext('2d').drawImage(video, 0, 0);

        const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
        inputFoto.value = dataUrl;

        fotoImg.src     = dataUrl;
        fotoImg.style.display  = 'block';
        video.style.display    = 'none';
        btnRetomar.style.display = 'block';
        fotoTomada = true;

        verificarListo();
    });

    // ── 4. Retomar foto ────────────────────────────────────────────────────
    btnRetomar && btnRetomar.addEventListener('click', function() {
        fotoImg.style.display    = 'none';
        video.style.display      = 'block';
        btnRetomar.style.display = 'none';
        inputFoto.value = '';
        fotoTomada = false;
        btnMarcar.disabled = true;
    });

    // ── 5. Habilitar botón marcar solo si GPS + foto OK ────────────────────
    function verificarListo() {
        btnMarcar.disabled = !(gpsOk && fotoTomada);
    }

    // ── 6. Envío con reintentos faciales ─────────────────────────────────────
    let intentosFacial = 0;
    const MAX_INTENTOS = 3;

    form && form.addEventListener('submit', async function(e) {
        e.preventDefault();

        btnMarcar.classList.add('loading');
        btnMarcar.disabled = true;

        const formData = new FormData(form);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });

            if (response.ok) {
                if (stream) stream.getTracks().forEach(t => t.stop());
                window.location.href = '{{ route("checkin.home") }}';
                return;
            }

            const data = await response.json();

            if (response.status === 422 && data.facial === false) {
                intentosFacial++;

                if (intentosFacial < MAX_INTENTOS) {
                    const restantes = MAX_INTENTOS - intentosFacial;
                    showAlert('⚠️ Rostro no reconocido. Te quedan ' + restantes + ' intento(s). Toma otra foto.', 'amarillo');

                    fotoImg.style.display    = 'none';
                    video.style.display      = 'block';
                    btnRetomar.style.display = 'none';
                    inputFoto.value = '';
                    fotoTomada = false;
                    btnMarcar.disabled = true;
                    btnMarcar.classList.remove('loading');
                } else {
                    if (stream) stream.getTracks().forEach(t => t.stop());
                    showAlert('❌ Marcación rechazada tras 3 intentos fallidos. Contacta a RR.HH.', 'rojo');
                    btnMarcar.classList.remove('loading');
                }
            } else {
                showAlert('❌ Error al registrar marcación. Intenta nuevamente.', 'rojo');
                btnMarcar.classList.remove('loading');
                btnMarcar.disabled = false;
            }

        } catch(err) {
            showAlert('❌ Error de conexión. Verifica tu internet.', 'rojo');
            btnMarcar.classList.remove('loading');
            btnMarcar.disabled = false;
        }
    });

    function showAlert(msg, tipo) {
        let alerta = document.getElementById('alerta-facial');
        if (!alerta) {
            alerta = document.createElement('div');
            alerta.id = 'alerta-facial';
            alerta.style.cssText = 'padding:12px 16px; border-radius:10px; font-size:13px; margin-top:12px; font-weight:600;';
            form.parentNode.insertBefore(alerta, form);
        }
        alerta.textContent = msg;
        alerta.style.background = tipo === 'rojo' ? '#fde8e8' : '#fef9e7';
        alerta.style.color = tipo === 'rojo' ? '#c0392b' : '#b7770d';
    }

    // ── Iniciar ────────────────────────────────────────────────────────────
    iniciarCamara();

})();
</script>
@endpush
