<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar Empresa — RLabsRH</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-lg px-4">

        {{-- Logo --}}
        <div class="text-center mb-8">
            <img src="{{ asset('images/logo-dark.svg') }}" alt="RLabsRH" class="h-12 mx-auto mb-3">
            <h1 class="text-2xl font-bold text-gray-800">Selecciona la empresa</h1>
            <p class="text-gray-500 text-sm mt-1">¿Con qué empresa vas a trabajar hoy?</p>
        </div>

        {{-- Cards de empresas --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach($companies as $company)
            <form method="POST" action="{{ route('company.select') }}">
                @csrf
                <input type="hidden" name="company_id" value="{{ $company->id }}">
                <button type="submit" class="w-full text-left bg-white rounded-2xl shadow-sm border-2 border-transparent hover:border-blue-500 hover:shadow-md transition-all p-6 group">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center group-hover:bg-blue-100 transition-colors">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 group-hover:text-blue-700 transition-colors">
                                {{ $company->razon_social }}
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">RUC {{ $company->ruc }}</p>
                        </div>
                    </div>
                </button>
            </form>
            @endforeach
        </div>

        {{-- Footer --}}
        <p class="text-center text-xs text-gray-400 mt-8">
            Sesión iniciada como <strong>{{ auth()->user()->name }}</strong> ·
            <a href="{{ route('filament.admin.auth.logout') }}" class="text-red-400 hover:underline"
               onclick="event.preventDefault(); document.getElementById('logout-form').submit()">
                Cerrar sesión
            </a>
        </p>
        <form id="logout-form" method="POST" action="{{ route('filament.admin.auth.logout') }}" class="hidden">
            @csrf
        </form>

    </div>
</body>
</html>
