<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Planilla de {{ $periodoLabel }}</x-slot>
        <x-slot name="description">Asistencia del {{ $rango }}</x-slot>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
            @foreach ($pasos as $paso)
                <a href="{{ $paso['url'] }}" style="display:block;text-decoration:none;color:inherit;border:1px solid rgba(128,128,128,.25);border-radius:12px;padding:12px 14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:4px;">
                        <strong style="font-size:14px;">{{ $paso['titulo'] }}</strong>
                        <x-filament::badge :color="$paso['ok'] ? 'success' : 'warning'">
                            {{ $paso['ok'] ? 'Listo' : 'Pendiente' }}
                        </x-filament::badge>
                    </div>
                    <div style="font-size:12.5px;opacity:.7;">{{ $paso['detalle'] }}</div>
                </a>
            @endforeach
        </div>

        @if ($neto > 0)
            <div style="display:flex;gap:32px;flex-wrap:wrap;margin-top:16px;padding-top:12px;border-top:1px solid rgba(128,128,128,.25);">
                <div>
                    <div style="font-size:12px;opacity:.7;">Neto a pagar (planilla mensual)</div>
                    <div style="font-size:22px;font-weight:650;">S/ {{ number_format($neto, 2) }}</div>
                </div>
                <div>
                    <div style="font-size:12px;opacity:.7;">Costo empresa (bruto + EsSalud)</div>
                    <div style="font-size:22px;font-weight:650;">S/ {{ number_format($costo, 2) }}</div>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
