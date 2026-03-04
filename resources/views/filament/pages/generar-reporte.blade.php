<x-filament-panels::page>

    {{-- Formulario de filtros --}}
    <x-filament-panels::form wire:submit="exportarPDF">
        {{ $this->form }}
    </x-filament-panels::form>

    {{-- ── PANEL DE VISTA PREVIA ───────────────────────────────────────── --}}
    @if($mostrarPrevia)
    <div class="mt-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">

        {{-- Barra superior del panel --}}
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
            <div class="flex items-center gap-2">
                @if($tipoPrevia === 'pdf')
                    <x-heroicon-o-document class="w-5 h-5 text-red-500" />
                    <span class="font-semibold text-sm text-gray-700 dark:text-gray-200">
                        Vista Previa — Reporte PDF
                    </span>
                @else
                    <x-heroicon-o-table-cells class="w-5 h-5 text-green-500" />
                    <span class="font-semibold text-sm text-gray-700 dark:text-gray-200">
                        Vista Previa — Datos Excel
                    </span>
                @endif
            </div>
            <button
                wire:click="cerrarPrevia"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition"
                title="Cerrar vista previa"
            >
                <x-heroicon-o-x-mark class="w-5 h-5" />
            </button>
        </div>

        {{-- Contenido: iframe para PDF, tabla para Excel --}}
        @if($tipoPrevia === 'pdf')
            {{-- Contenedor con shadow DOM para aislar estilos del reporte --}}
            <div class="w-full overflow-auto" style="height: 75vh; background: #fff; color-scheme: light;">
                <div id="report-preview-host" style="color-scheme: light; background: #fff; color: #333;"></div>
            </div>

            @script
            <script>
                $nextTick(() => {
                    var host = document.getElementById('report-preview-host');
                    if (!host) return;
                    // Usar shadow DOM para aislar los estilos del reporte de Filament
                    if (!host.shadowRoot) {
                        host.attachShadow({ mode: 'open' });
                    }
                    host.shadowRoot.innerHTML = '<style>:host { color-scheme: light; } * { color-scheme: light; }</style>' + @json($htmlPrevia);
                });
            </script>
            @endscript

        @elseif($tipoPrevia === 'excel')
            {{-- Tabla scrollable con los datos del Excel --}}
            <div class="overflow-auto" style="max-height: 70vh;">
                <table class="w-full text-xs border-collapse">
                    <thead class="sticky top-0 z-10">
                        <tr>
                            @foreach($excelHeaders as $header)
                            <th class="px-3 py-2 text-left font-semibold text-white bg-green-700 border border-green-600 whitespace-nowrap">
                                {{ $header }}
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($excelData as $i => $row)
                        <tr class="{{ $i % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50 dark:bg-gray-800' }} hover:bg-green-50 dark:hover:bg-green-900/20">
                            @foreach($row as $cell)
                            <td class="px-3 py-1.5 border border-gray-200 dark:border-gray-700 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                {{ $cell }}
                            </td>
                            @endforeach
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ count($excelHeaders) }}" class="text-center py-8 text-gray-400">
                                No hay registros para el período seleccionado.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pie con conteo de filas --}}
            <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs text-gray-500">
                {{ count($excelData) }} registro(s) encontrado(s)
            </div>
        @endif

    </div>
    @endif

</x-filament-panels::page>