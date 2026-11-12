<?php

namespace App\Support;

final class BancoPracticaUnfv
{
    public static function documento(): array
    {
        return json_decode(file_get_contents(database_path('data/unfv_preguntas_practica.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function version(): string
    {
        return substr(hash_file('sha256', database_path('data/unfv_preguntas_practica.json')), 0, 12);
    }

    public static function area(string $prefijo): string
    {
        return 'UNFV práctica '.self::version().' · '.PerfilExamenCni::CURSOS[$prefijo][0];
    }
}
