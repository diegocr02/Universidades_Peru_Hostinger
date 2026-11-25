<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorMensaje extends Model
{
    protected $table = 'tutor_mensajes';

    protected $fillable = ['conversacion_id', 'rol', 'tipo', 'contenido', 'datos', 'origen', 'tokens'];

    protected function casts(): array
    {
        return ['datos' => 'array'];
    }
}
