<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Support\Ubigeo;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Las direcciones ya cargadas suelen terminar con "DEPARTAMENTO-PROVINCIA-DISTRITO"
 * (ej. "AV. 28 DE JULIO 368 DPTO. 603 LIMA-LIMA-JESUS MARIA"). Este comando lee
 * ese final y completa los tres selects de la ficha. Sin --aplicar solo muestra
 * lo que haría y no toca las fichas que ya tienen ubigeo.
 */
class InferirUbigeoDeDirecciones extends Command
{
    protected $signature = 'rrhh:inferir-ubigeo {--aplicar : Guardar (sin esto solo simula)}';
    protected $description = 'Completa departamento, provincia y distrito desde el final de la dirección';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $this->info($aplicar ? '== APLICANDO ==' : '== SIMULACIÓN (agrega --aplicar para guardar) ==');

        $norm = fn (string $s) => Str::upper(Str::ascii(trim(preg_replace('/\s+/', ' ', $s))));

        $mapaDep = [];
        foreach (Ubigeo::departamentos() as $id => $nombre) {
            $mapaDep[$norm($nombre)] = $id;
        }

        $tabla = [];
        $sinMatch = [];

        $empleados = Employee::withoutGlobalScopes()
            ->whereNotNull('direccion')->where('direccion', '!=', '')
            ->whereNull('ubigeo_distrito')
            ->get();

        foreach ($empleados as $e) {
            // Los tres últimos tramos separados por guion: DEP-PROV-DIST
            $partes = preg_split('/\s*-\s*/', trim($e->direccion));
            if (count($partes) < 3) {
                $sinMatch[] = [$e->id, $e->apellidos, $e->direccion];
                continue;
            }
            [$dep, $prov, $dist] = array_slice($partes, -3);

            // El primer tramo trae la calle y termina con el departamento
            // ("... DPTO. 603 LIMA"): se busca el departamento al final del texto.
            $depNorm = $norm($dep);
            $depId = null;
            foreach ($mapaDep as $nombre => $id) {
                if ($depNorm === $nombre || str_ends_with($depNorm, ' ' . $nombre)) {
                    if ($depId === null || strlen($nombre) > strlen($mejorNombre)) {
                        $depId = $id;
                        $mejorNombre = $nombre;
                    }
                }
            }
            $provId = $distId = null;

            if ($depId) {
                foreach (Ubigeo::provincias($depId) as $id => $nombre) {
                    if ($norm($nombre) === $norm($prov)) {
                        $provId = $id;
                        break;
                    }
                }
            }
            if ($provId) {
                foreach (Ubigeo::distritos($provId) as $id => $nombre) {
                    if ($norm($nombre) === $norm($dist)) {
                        $distId = $id;
                        break;
                    }
                }
            }

            if (! $distId) {
                $sinMatch[] = [$e->id, $e->apellidos, $e->direccion];
                continue;
            }

            $tabla[] = [$e->id, $e->apellidos, Ubigeo::nombreDepartamento($depId), Ubigeo::nombreProvincia($provId), Ubigeo::nombreDistrito($distId)];

            if ($aplicar) {
                $e->forceFill([
                    'ubigeo_departamento' => $depId,
                    'ubigeo_provincia'    => $provId,
                    'ubigeo_distrito'     => $distId,
                ])->save();
            }
        }

        $this->table(['id', 'Trabajador', 'Departamento', 'Provincia', 'Distrito'], $tabla);

        if ($sinMatch) {
            $this->warn(count($sinMatch) . ' dirección(es) sin ubigeo reconocible; se completan a mano en la ficha:');
            $this->table(['id', 'Trabajador', 'Dirección'], $sinMatch);
        }

        return self::SUCCESS;
    }
}
