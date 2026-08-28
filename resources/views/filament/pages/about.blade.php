<x-filament-panels::page>
    <div class="flex justify-center py-8">
        <div class="w-full max-w-sm bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8 text-center">
            <img src="{{ asset('images/logo-dark.svg') }}" alt="RLabs" class="h-12 mx-auto mb-4 dark:hidden" />
            <img src="{{ asset('images/logo-light.svg') }}" alt="RLabs" class="h-12 mx-auto mb-4 hidden dark:block" />

            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">RLabs RRHH</h2>
            <p class="text-sm text-gray-400">Versión {{ config('app.version', '4.0.0') }}</p>

            <div class="border-t border-gray-100 dark:border-gray-700 my-4"></div>

            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Empresa activa:</strong> {{ $this->getCompany()?->razon_social ?? '—' }}
            </div>

            <div class="border-t border-gray-100 dark:border-gray-700 my-4"></div>

            <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
                <p class="font-semibold text-gray-900 dark:text-white">RLabs</p>
                <p>contacto@rlabspe.com</p>
                <p>www.rlabspe.com</p>
            </div>
        </div>
    </div>
</x-filament-panels::page>