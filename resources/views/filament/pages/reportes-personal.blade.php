<x-filament-panels::page>

    <x-filament-panels::form>
        {{ $this->form }}
    </x-filament-panels::form>

    <div class="mt-6 space-y-6">
        @foreach($this->gruposDeReportes() as $grupo => $reportes)
        <div>
            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-2">{{ $grupo }}</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($reportes as $reporte)
                <x-filament::button
                    wire:click="{{ $reporte['metodo'] }}"
                    wire:loading.attr="disabled"
                    icon="{{ $reporte['icon'] }}"
                    color="{{ $reporte['color'] }}"
                    size="sm"
                >
                    {{ $reporte['label'] }}
                </x-filament::button>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
        Elige la empresa (y opcionalmente sede/área), luego toca el reporte que quieras descargar. El periodo (mes/año) aplica a: Vacaciones del Mes, Boletas Pendientes, Altas y Bajas, Puntualidad por Área y Cumpleaños del Mes.
    </div>

</x-filament-panels::page>
