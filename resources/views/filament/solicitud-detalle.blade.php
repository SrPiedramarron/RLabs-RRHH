<div class="space-y-3 text-sm">
    <div class="grid grid-cols-2 gap-3">
        <div>
            <div class="text-gray-500">Trabajador</div>
            <div class="font-semibold">{{ $s->employee->nombre_completo }}</div>
        </div>
        <div>
            <div class="text-gray-500">Tipo</div>
            <div class="font-semibold">{{ $s->tipo_label }}</div>
        </div>

        @if($s->tipo === 'correccion_horas')
            <div>
                <div class="text-gray-500">Fecha a corregir</div>
                <div class="font-semibold">{{ $s->fecha_registro?->format('d/m/Y') }}</div>
            </div>
            <div>
                <div class="text-gray-500">Horas solicitadas</div>
                <div class="font-semibold">
                    {{ $s->hora_entrada_solicitada ? \Carbon\Carbon::parse($s->hora_entrada_solicitada)->format('H:i') : '—' }}
                    →
                    {{ $s->hora_salida_solicitada ? \Carbon\Carbon::parse($s->hora_salida_solicitada)->format('H:i') : '—' }}
                </div>
            </div>
        @elseif($s->fecha_inicio)
            <div>
                <div class="text-gray-500">Desde</div>
                <div class="font-semibold">{{ $s->fecha_inicio->format('d/m/Y') }}</div>
            </div>
            <div>
                <div class="text-gray-500">Hasta</div>
                <div class="font-semibold">{{ $s->fecha_fin?->format('d/m/Y') }}</div>
            </div>
        @endif

        <div>
            <div class="text-gray-500">Enviada</div>
            <div class="font-semibold">{{ $s->created_at->format('d/m/Y H:i') }}</div>
        </div>
        <div>
            <div class="text-gray-500">Estado</div>
            <div class="font-semibold">{{ $s->estado === 'pendiente_jefe' ? 'Espera al jefe' : ucfirst($s->estado) }}</div>
        </div>
    </div>

    <div>
        <div class="text-gray-500">Motivo (texto completo)</div>
        <div class="mt-1 rounded-lg border border-gray-200 dark:border-gray-700 p-3 whitespace-pre-line break-words">{{ $s->motivo ?: '—' }}</div>
    </div>

    @if($s->jefe)
        <div>
            <div class="text-gray-500">Jefe directo</div>
            <div class="font-semibold">{{ $s->jefe->nombre_completo }}</div>
            @if($s->comentario_jefe)
                <div class="mt-1 whitespace-pre-line break-words">Comentario del jefe: {{ $s->comentario_jefe }}</div>
            @elseif($s->escalada_at)
                <div class="mt-1">Sin respuesta del jefe: pasó a RRHH el {{ $s->escalada_at->format('d/m/Y H:i') }}.</div>
            @endif
        </div>
    @endif

    @if($s->comentario_revision)
        <div>
            <div class="text-gray-500">Comentario de la revisión</div>
            <div class="mt-1 whitespace-pre-line break-words">{{ $s->comentario_revision }}</div>
        </div>
    @endif

    @if($s->adjunto_path)
        <div>
            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($s->adjunto_path) }}" target="_blank" class="text-primary-600 underline">Ver adjunto</a>
        </div>
    @endif
</div>
