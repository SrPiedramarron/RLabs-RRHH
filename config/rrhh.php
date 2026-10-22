<?php

return [
    // Correos (separados por coma) que reciben el aviso semanal si la auditoría de cruces entre empresas
    // encuentra errores. Si está vacío, se envía a los usuarios con rol super_admin.
    'auditoria_correos' => env('AUDITORIA_CORREOS', ''),
];
