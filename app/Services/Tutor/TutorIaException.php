<?php

namespace App\Services\Tutor;

use RuntimeException;

/** Fallo controlado del proveedor de IA, con un motivo que la interfaz sabe explicar. */
class TutorIaException extends RuntimeException
{
    public const SIN_CONFIGURAR = 'sin_configurar';

    public const LIMITE = 'limite';

    public const NO_DISPONIBLE = 'no_disponible';

    public const TIEMPO = 'tiempo';

    public const EXAMEN = 'examen_activo';

    public const OCUPADO = 'ocupado';

    public const INVALIDO = 'invalido';

    public function __construct(public readonly string $motivo, string $mensaje)
    {
        parent::__construct($mensaje);
    }

    /** ¿Tiene sentido ofrecer «Reintentar»? */
    public function reintentable(): bool
    {
        return in_array($this->motivo, [self::NO_DISPONIBLE, self::TIEMPO], true);
    }
}
