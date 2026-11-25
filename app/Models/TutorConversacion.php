<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TutorConversacion extends Model
{
    protected $table = 'tutor_conversaciones';

    protected $fillable = ['usuario_id', 'area_codigo', 'contexto'];

    protected function casts(): array
    {
        return ['contexto' => 'array'];
    }

    public function mensajes(): HasMany
    {
        // Sin orden fijo: cada consulta indica el suyo (latest/oldest) para no anular latest('id').
        return $this->hasMany(TutorMensaje::class, 'conversacion_id');
    }
}
