<?php

namespace App\Livewire\Alumno\Tutor;

use App\Models\TutorConversacion;
use App\Models\Usuario;
use App\Services\Alumno\ContextoAlumnoService;
use App\Services\Tutor\TutorIaException;
use App\Services\Tutor\TutorService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pantalla del Tutor CNI. Solo guarda estado de interfaz: la identidad, la
 * universidad y la conversación se resuelven en el servidor en cada petición.
 */
class TutorChat extends Component
{
    public string $mensaje = '';

    public ?string $error = null;

    public bool $puedeReintentar = false;

    // El navegador no puede cambiarla; igualmente se valida su propietario en cada uso.
    #[Locked]
    public ?int $conversacionId = null;

    public function mount(): void
    {
        [$usuario, $ctx] = $this->sesion();
        $servicio = app(TutorService::class);
        $conversacion = $servicio->conversacion($usuario, $ctx);
        $this->conversacionId = $conversacion->id;
        $this->error = session('tutor_error');

        // «Explicación del tutor» desde la revisión o la práctica: se explica una vez y se limpia
        // la URL para que recargar no repita la explicación. El servicio valida el acceso.
        $pregunta = request()->integer('pregunta');
        if ($pregunta > 0) {
            try {
                $servicio->accion($usuario, $ctx, $conversacion, 'pregunta_explicar', (string) $pregunta);
            } catch (TutorIaException $e) {
                session()->flash('tutor_error', $e->getMessage());
            }
            $this->redirectRoute('alumno.tutor');
        }
    }

    public function iniciar(string $flujo): void
    {
        $this->ejecutar(fn ($s, $u, $ctx, $c) => $s->accion($u, $ctx, $c, 'iniciar', $flujo));
    }

    public function elegir(string $accion, string $valor = ''): void
    {
        // Opciones del chat: TutorService valida la acción, el valor y la propiedad de cada dato.
        $this->ejecutar(fn ($s, $u, $ctx, $c) => $s->accion($u, $ctx, $c, $accion, $valor));
    }

    public function responder(string $letra): void
    {
        $this->ejecutar(fn ($s, $u, $ctx, $c) => $s->accion($u, $ctx, $c, 'responder', $letra));
    }

    public function ayuda(string $tipo): void
    {
        $this->ejecutar(fn ($s, $u, $ctx, $c) => $s->accion($u, $ctx, $c, 'ayuda', $tipo));
    }

    public function enviar(): void
    {
        $texto = $this->mensaje;
        $this->ejecutar(function ($s, $u, $ctx, $c) use ($texto) {
            $s->enviar($u, $ctx, $c, $texto);
            $this->mensaje = '';
        });
    }

    public function reintentar(): void
    {
        $this->ejecutar(fn ($s, $u, $ctx, $c) => $s->reintentar($u, $ctx, $c));
    }

    public function nuevaConversacion(): void
    {
        [$usuario, $ctx] = $this->sesion();
        $this->conversacionId = app(TutorService::class)->nueva($usuario, $ctx)->id;
        $this->reset('mensaje', 'error', 'puedeReintentar');
        $this->dispatch('tutor-actualizado');
    }

    public function render()
    {
        [$usuario, $ctx] = $this->sesion();
        $servicio = app(TutorService::class);
        $conversacion = $this->conversacionActual($usuario, $ctx);
        $mensajes = $servicio->mensajes($conversacion);
        $pendiente = $servicio->pendiente($conversacion);

        return view('livewire.alumno.tutor.tutor-chat', [
            'usuario' => $usuario,
            'ctx' => $ctx,
            'mensajes' => $mensajes,
            'pendiente' => $pendiente,
            'examenActivo' => app(ContextoAlumnoService::class)->examenActivo($usuario),
            'iaConfigurada' => $servicio->iaConfigurada(),
            'iaDemo' => $servicio->iaDemo(),
            'restantes' => $servicio->consultasIaRestantes($usuario),
        ])->layout('layouts.app');
    }

    private function ejecutar(callable $accion): void
    {
        $this->reset('error', 'puedeReintentar');
        [$usuario, $ctx] = $this->sesion();
        $servicio = app(TutorService::class);

        try {
            $accion($servicio, $usuario, $ctx, $this->conversacionActual($usuario, $ctx));
        } catch (TutorIaException $e) {
            $this->error = $e->getMessage();
            $this->puedeReintentar = $e->reintentable();
        }

        $this->dispatch('tutor-actualizado');
    }

    /** Conversación del alumno en su universidad actual (si cambió de universidad, otra). */
    private function conversacionActual(Usuario $usuario, array $ctx): TutorConversacion
    {
        $conversacion = app(TutorService::class)->conversacion($usuario, $ctx, $this->conversacionId);
        $this->conversacionId = $conversacion->id;

        return $conversacion;
    }

    /** @return array{0: Usuario, 1: array} */
    private function sesion(): array
    {
        $usuario = Auth::guard('web')->user();
        abort_unless($usuario instanceof Usuario && $usuario->activo && $usuario->rol === 'estudiante', 403);
        abort_unless(config('tutor.enabled'), 404);
        $ctx = app(ContextoAlumnoService::class)->contexto($usuario);
        abort_unless($ctx !== null, 403, 'Primero elige tu universidad desde el panel.');

        return [$usuario, $ctx];
    }
}
