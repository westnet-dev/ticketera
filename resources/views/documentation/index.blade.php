@php
    $sections = [
        'que-es' => __('¿Qué es la plataforma?'),
        'ciclo-de-vida' => __('Ciclo de vida de un ticket'),
        'crear-ticket' => __('Crear un ticket'),
        'borradores' => __('Borradores'),
        'triage' => __('Revisión inicial (triage)'),
        'estados' => __('Estados del ticket'),
        'seguimiento' => __('Chat e historial'),
        'validacion' => __('Validar la resolución'),
        'limite' => __('Límite de tickets'),
        'mis-tickets' => __('Tus pestañas de tickets'),
        'cuenta' => __('Tu cuenta'),
        'faq' => __('Preguntas frecuentes'),
    ];

    if (auth()->user()->isAdmin()) {
        $sections['administracion'] = __('Guía para administradores');
    }

    $statuses = [
        'draft' => __('Lo estás redactando. Solo vos lo ves y todavía no fue enviado.'),
        'open' => __('Fue enviado y aprobado. Está en la cola esperando que alguien lo tome.'),
        'in_progress' => __('Un integrante del equipo está trabajando en tu pedido.'),
        'paused' => __('El trabajo se detuvo momentáneamente, por ejemplo mientras se espera información o a un tercero.'),
        'resolved' => __('El equipo considera que el pedido está resuelto y te pide que lo valides.'),
        'cancelled' => __('El pedido se cerró sin resolverse (duplicado, ya no hace falta, fuera de alcance, etc.).'),
    ];
@endphp

<x-layouts::app :title="__('Documentación')">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">{{ __('Guía de uso de la plataforma') }}</flux:heading>
            <flux:subheading>
                {{ __('Todo lo que necesitás saber para cargar pedidos, seguirlos y saber qué esperar en cada paso.') }}
            </flux:subheading>
        </div>

        <div class="grid gap-6 lg:grid-cols-4">
            <div class="flex flex-col gap-10 lg:col-span-3">
                {{-- ¿Qué es? --}}
                <section id="que-es" class="flex scroll-mt-6 flex-col gap-3">
                    <flux:heading size="lg">{{ $sections['que-es'] }}</flux:heading>
                    <flux:text>
                        {{ __('Es el canal oficial para hacerle pedidos al equipo: reportar un problema, solicitar un cambio o hacer una consulta. Cada pedido es un ticket, y cada ticket tiene su propio chat, su historial de cambios y un estado que te dice en qué etapa está.') }}
                    </flux:text>
                    <flux:text>
                        {{ __('Usar la plataforma en lugar de mails o mensajes sueltos permite que nada se pierda, que el equipo priorice según la necesidad real y que vos puedas ver el avance en cualquier momento.') }}
                    </flux:text>
                </section>

                {{-- Ciclo de vida --}}
                <section id="ciclo-de-vida" class="flex scroll-mt-6 flex-col gap-4">
                    <flux:heading size="lg">{{ $sections['ciclo-de-vida'] }}</flux:heading>
                    <flux:text>{{ __('Así recorre la plataforma un ticket típico, desde que lo cargás hasta que se cierra:') }}</flux:text>

                    <ol class="flex flex-col gap-3">
                        @foreach ([
                            [__('Lo creás'), __('Completás el formulario. Podés guardarlo como borrador y enviarlo más tarde.')],
                            [__('Revisión inicial'), __('Un administrador revisa que el pedido esté claro y completo. Si lo aprueba, entra a la cola de trabajo; si lo rechaza, te explica el motivo en el chat para que lo corrijas.')],
                            [__('Se asigna y se trabaja'), __('Alguien del equipo lo toma y el ticket pasa a En Progreso. Puede pausarse si hace falta esperar algo.')],
                            [__('Se marca como resuelto'), __('Cuando el equipo termina, lo marca como Resuelto y te pide que valides el resultado.')],
                            [__('Lo validás'), __('Confirmás que quedó resuelto y calificás la solución, o indicás qué faltó y el ticket vuelve a En Progreso.')],
                        ] as $index => [$title, $body])
                            <li class="flex gap-3">
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">
                                    {{ $index + 1 }}
                                </span>
                                <div>
                                    <p class="font-medium">{{ $title }}</p>
                                    <flux:text>{{ $body }}</flux:text>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>

                {{-- Crear ticket --}}
                <section id="crear-ticket" class="flex scroll-mt-6 flex-col gap-4">
                    <flux:heading size="lg">{{ $sections['crear-ticket'] }}</flux:heading>
                    <flux:text>
                        {{ __('Desde Tickets, tocá "Nuevo ticket". Estos son los campos del formulario:') }}
                    </flux:text>

                    <dl class="flex flex-col gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                        @foreach ([
                            [__('Título'), __('Una frase corta que resuma el pedido (mínimo 5 caracteres). Ej.: "No puedo exportar el reporte mensual".')],
                            [__('Descripción'), __('Explicá qué pasa, desde cuándo, qué esperabas que pasara y qué pasos seguiste (mínimo 10 caracteres). Cuanto más detalle, más rápido se resuelve.')],
                            [__('Imágenes (opcional)'), __('Hasta 5 imágenes por ticket, de hasta 2 MB cada una. Una captura de pantalla del error suele ahorrar varias idas y vueltas.')],
                        ] as [$term, $definition])
                            <div>
                                <dt class="font-medium">{{ $term }}</dt>
                                <dd><flux:text>{{ $definition }}</flux:text></dd>
                            </div>
                        @endforeach
                    </dl>

                    <flux:text>
                        {{ __('Además, calificás el pedido en tres escalas del 1 al 10. El equipo las usa para ordenar la cola de trabajo, así que conviene ser realista:') }}
                    </flux:text>

                    <div class="grid gap-3 md:grid-cols-3">
                        @foreach ([
                            [__('Prioridad'), __('Qué tan importante es este pedido comparado con tus otros pedidos.')],
                            [__('Urgencia'), __('Qué tan rápido necesitás que se resuelva.')],
                            [__('Impacto'), __('Cuánto afecta a los clientes o al negocio: a una persona, a un área entera, a toda la empresa.')],
                        ] as [$title, $body])
                            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                                <flux:heading size="sm">{{ $title }}</flux:heading>
                                <flux:text class="mt-1">{{ $body }}</flux:text>
                            </div>
                        @endforeach
                    </div>

                    <flux:callout icon="light-bulb" color="blue">
                        <flux:callout.text>
                            {{ __('Si marcás todo con 10, el equipo no puede distinguir lo verdaderamente crítico. Reservá los valores altos para lo que realmente lo amerita.') }}
                        </flux:callout.text>
                    </flux:callout>
                </section>

                {{-- Borradores --}}
                <section id="borradores" class="flex scroll-mt-6 flex-col gap-3">
                    <flux:heading size="lg">{{ $sections['borradores'] }}</flux:heading>
                    <flux:text>
                        {{ __('Si todavía no tenés toda la información, usá "Guardar como borrador". Para guardarlo solo hace falta el título; el resto lo completás después.') }}
                    </flux:text>
                    <ul class="ms-5 list-disc space-y-1 text-sm text-neutral-600 dark:text-neutral-300">
                        <li>{{ __('Los borradores son privados: nadie del equipo los ve hasta que los enviás.') }}</li>
                        <li>{{ __('No cuentan para el límite de tickets.') }}</li>
                        <li>{{ __('Los encontrás en la pestaña Borradores, donde podés editarlos, enviarlos o eliminarlos.') }}</li>
                        <li>{{ __('Al enviarlo, se exigen todos los campos obligatorios y se controla el límite de tickets.') }}</li>
                    </ul>
                </section>

                {{-- Triage --}}
                <section id="triage" class="flex scroll-mt-6 flex-col gap-3">
                    <flux:heading size="lg">{{ $sections['triage'] }}</flux:heading>
                    <flux:text>
                        {{ __('Todo ticket nuevo pasa primero por una revisión de un administrador. Mientras tanto vas a ver la etiqueta "Pendiente de triage".') }}
                    </flux:text>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded-xl border border-green-500/50 bg-green-500/10 p-4">
                            <flux:heading size="sm">{{ __('Si se aprueba') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('El ticket entra a la cola de trabajo con estado Abierto y queda disponible para asignarse.') }}</flux:text>
                        </div>
                        <div class="rounded-xl border border-red-500/50 bg-red-500/10 p-4">
                            <flux:heading size="sm">{{ __('Si se rechaza') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Recibís el motivo como mensaje en el chat. En el mismo ticket aparece un formulario para corregirlo y "Reenviar para ser aprobado". No hace falta crear uno nuevo.') }}</flux:text>
                        </div>
                    </div>
                </section>

                {{-- Estados --}}
                <section id="estados" class="flex scroll-mt-6 flex-col gap-3">
                    <flux:heading size="lg">{{ $sections['estados'] }}</flux:heading>
                    <flux:text>{{ __('El estado indica en qué etapa está tu pedido. Solo el equipo puede cambiarlo.') }}</flux:text>

                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Estado') }}</flux:table.column>
                            <flux:table.column>{{ __('Qué significa') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($statuses as $status => $meaning)
                                <flux:table.row>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="\App\Models\Ticket::colorForStatus($status)">
                                            {{ \App\Models\Ticket::labelForStatus($status) }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="whitespace-normal">{{ $meaning }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </section>

                {{-- Seguimiento --}}
                <section id="seguimiento" class="flex scroll-mt-6 flex-col gap-3">
                    <flux:heading size="lg">{{ $sections['seguimiento'] }}</flux:heading>
                    <flux:text>
                        {{ __('Al abrir un ticket ves su descripción, las imágenes adjuntas (hacé clic para ampliarlas), el chat y, a la derecha, sus propiedades.') }}
                    </flux:text>
                    <ul class="ms-5 list-disc space-y-1 text-sm text-neutral-600 dark:text-neutral-300">
                        <li>{{ __('Chat: es la conversación con el equipo sobre ese pedido. Se actualiza solo cada pocos segundos, no hace falta recargar la página.') }}</li>
                        <li>{{ __('Historial: registra cada cambio de estado, de revisión, de validación y de asignación, con quién lo hizo y cuándo.') }}</li>
                        <li>{{ __('Si un administrador cargó el ticket por vos, lo vas a ver indicado en las propiedades. El ticket es tuyo igual y lo validás vos.') }}</li>
                    </ul>
                </section>

                {{-- Validación --}}
                <section id="validacion" class="flex scroll-mt-6 flex-col gap-3">
                    <flux:heading size="lg">{{ $sections['validacion'] }}</flux:heading>
                    <flux:text>
                        {{ __('Cuando el equipo marca tu ticket como Resuelto, te toca confirmar el resultado. Lo vas a ver en la pestaña "Por validar" y como aviso en el propio ticket.') }}
                    </flux:text>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                            <flux:heading size="sm" class="flex items-center gap-2">
                                {{ __('Confirmar resolución') }}
                                <span class="flex text-yellow-500">
                                    @for ($star = 1; $star <= 5; $star++)
                                        <flux:icon.star variant="solid" class="size-4" />
                                    @endfor
                                </span>
                            </flux:heading>
                            <flux:text class="mt-1">{{ __('Calificás la solución de 1 a 5 estrellas y el ticket queda cerrado. Tu calificación ayuda al equipo a mejorar.') }}</flux:text>
                        </div>
                        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                            <flux:heading size="sm">{{ __('No se resolvió') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Contás qué quedó pendiente. Tu mensaje se publica en el chat y el ticket vuelve a En Progreso para que el equipo siga trabajando.') }}</flux:text>
                        </div>
                    </div>
                    <flux:text>
                        {{ __('Solo la persona a cuyo nombre está el ticket puede validarlo.') }}
                    </flux:text>
                </section>

                {{-- Límite --}}
                <section id="limite" class="flex scroll-mt-6 flex-col gap-3">
                    <flux:heading size="lg">{{ $sections['limite'] }}</flux:heading>
                    <flux:text>
                        {{ __('Para que el equipo pueda atender todos los pedidos, hay un máximo de :max tickets sin cerrar a la vez. El límite se comparte entre todas las personas de un mismo área; si no tenés área asignada, se cuenta sobre tus propios tickets.', ['max' => $maxOpenTickets]) }}
                    </flux:text>
                    <flux:text>
                        {{ __('Cuentan los tickets Abiertos, En Progreso, Pausados y los que esperan revisión inicial. Los borradores, resueltos y cancelados no cuentan.') }}
                    </flux:text>

                    @unless (auth()->user()->isAdmin())
                        <flux:callout
                            icon="chart-bar"
                            :color="$openTicketCount >= $maxOpenTickets ? 'red' : 'zinc'"
                        >
                            <flux:callout.heading>
                                {{ $limitIsPerArea
                                    ? __('Tu área tiene :count de :max tickets sin cerrar.', ['count' => $openTicketCount, 'max' => $maxOpenTickets])
                                    : __('Tenés :count de :max tickets sin cerrar.', ['count' => $openTicketCount, 'max' => $maxOpenTickets]) }}
                            </flux:callout.heading>
                            @if ($openTicketCount >= $maxOpenTickets)
                                <flux:callout.text>
                                    {{ __('Mientras el límite esté completo no vas a poder crear ni enviar tickets nuevos. Podés seguir guardando borradores.') }}
                                </flux:callout.text>
                            @endif
                        </flux:callout>
                    @endunless
                </section>

                {{-- Mis tickets --}}
                <section id="mis-tickets" class="flex scroll-mt-6 flex-col gap-3">
                    <flux:heading size="lg">{{ $sections['mis-tickets'] }}</flux:heading>
                    <flux:text>{{ __('En la sección Tickets tus pedidos se organizan en pestañas:') }}</flux:text>
                    <dl class="flex flex-col gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                        @foreach ([
                            [__('En curso'), __('Tickets Abiertos, En Progreso o Pausados.')],
                            [__('Por validar'), __('Tickets resueltos que esperan tu confirmación. El número entre paréntesis indica cuántos tenés pendientes.')],
                            [__('Finalizados'), __('Tickets Resueltos o Cancelados.')],
                            [__('Borradores'), __('Pedidos que guardaste sin enviar.')],
                        ] as [$term, $definition])
                            <div>
                                <dt class="font-medium">{{ $term }}</dt>
                                <dd><flux:text>{{ $definition }}</flux:text></dd>
                            </div>
                        @endforeach
                    </dl>
                    <flux:text>{{ __('En los listados podés ordenar por prioridad, urgencia, impacto o fecha de creación tocando el encabezado de la columna.') }}</flux:text>
                </section>

                {{-- Cuenta --}}
                <section id="cuenta" class="flex scroll-mt-6 flex-col gap-3">
                    <flux:heading size="lg">{{ $sections['cuenta'] }}</flux:heading>
                    <flux:text>
                        {{ __('Desde el menú de tu usuario, en Herramientas, podés actualizar tu nombre y email (Perfil), cambiar tu contraseña (Seguridad) y elegir el tema claro u oscuro (Apariencia).') }}
                    </flux:text>
                    <flux:text>
                        {{ __('Las cuentas las crea un administrador. Si necesitás que den de alta a un compañero, que te cambien de área o que te restablezcan la contraseña, pedíselo a un administrador.') }}
                    </flux:text>
                </section>

                {{-- FAQ --}}
                <section id="faq" class="flex scroll-mt-6 flex-col gap-3">
                    <flux:heading size="lg">{{ $sections['faq'] }}</flux:heading>
                    <div class="flex flex-col divide-y divide-neutral-200 rounded-xl border border-neutral-200 dark:divide-neutral-700 dark:border-neutral-700">
                        @foreach ([
                            [__('¿Puedo editar un ticket después de enviarlo?'), __('No directamente. Si falta información o cambió algo, escribilo en el chat del ticket. La única excepción es cuando el ticket es rechazado en la revisión inicial: ahí podés corregirlo y reenviarlo.')],
                            [__('¿Por qué no puedo crear un ticket nuevo?'), __('Probablemente se alcanzó el límite de tickets sin cerrar de tu área. Revisá la sección Límite de tickets; cuando se resuelva o cancele alguno vas a poder crear otro. Mientras tanto podés guardarlo como borrador.')],
                            [__('¿Quién ve mis tickets?'), __('Vos y los administradores. Otros clientes, incluso de tu misma área, no ven tus tickets.')],
                            [__('¿Qué pasa si no valido un ticket resuelto?'), __('Queda en la pestaña "Por validar" hasta que respondas. Validarlo le confirma al equipo que el trabajo terminó.')],
                            [__('Me respondieron en el chat, ¿tengo que recargar?'), __('No, el chat se actualiza solo. Si igual no ves los mensajes, recargá la página.')],
                            [__('¿Puedo cancelar un ticket que ya no necesito?'), __('Pedilo en el chat del ticket y un administrador lo va a pasar a Cancelado.')],
                        ] as [$question, $answer])
                            <details class="group p-4">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 font-medium">
                                    {{ $question }}
                                    <flux:icon.chevron-down class="size-4 shrink-0 transition-transform group-open:rotate-180" />
                                </summary>
                                <flux:text class="mt-2">{{ $answer }}</flux:text>
                            </details>
                        @endforeach
                    </div>
                </section>

                @if (auth()->user()->isAdmin())
                    <flux:separator />

                    <section id="administracion" class="flex scroll-mt-6 flex-col gap-4">
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">{{ $sections['administracion'] }}</flux:heading>
                            <flux:badge size="sm" color="blue">{{ __('Solo admins') }}</flux:badge>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach ([
                                [__('Triage'), __('Lista los tickets pendientes de revisión inicial. Al aprobar, el ticket pasa a la cola de trabajo; al rechazar, tenés que escribir un motivo que se publica en el chat y el cliente puede corregirlo y reenviarlo.')],
                                [__('Tickets'), __('Cola de trabajo con los tickets aprobados. Podés filtrar por sin asignar, resueltos o cancelados, ordenar por prioridad, urgencia, impacto o fecha, y asignar cada ticket a un administrador.')],
                                [__('Cambio de estado'), __('Desde el detalle de un ticket aprobado cambiás su estado. Al pasarlo a Resuelto se le pide validación al autor; si lo sacás de Resuelto antes de que responda, esa solicitud se retira.')],
                                [__('Mis tickets'), __('Muestra los tickets que cargaste para vos y los que tenés asignados.')],
                                [__('Crear a nombre de un cliente'), __('Al crear un ticket podés elegir un cliente como autor. El ticket queda aprobado de entrada, no puede guardarse como borrador y la validación la hace el cliente. Los tickets de administradores no tienen límite.')],
                                [__('Usuarios'), __('Alta de usuarios, cambio de rol (cliente o admin), asignación de área, restablecimiento de contraseña y baja.')],
                                [__('Áreas'), __('Creá, renombrá o eliminá áreas y asigná un área a los usuarios que todavía no tienen. El límite de tickets se calcula por área.')],
                                [__('Configuración del límite'), __('En Herramientas > Tickets definís el máximo de tickets sin cerrar por área (hoy: :max). Aplica por igual a todas las áreas.', ['max' => $maxOpenTickets])],
                            ] as [$title, $body])
                                <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                                    <flux:heading size="sm">{{ $title }}</flux:heading>
                                    <flux:text class="mt-1">{{ $body }}</flux:text>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            <nav class="lg:col-span-1" aria-label="{{ __('Índice') }}">
                <div class="flex flex-col gap-1 rounded-xl border border-neutral-200 p-4 lg:sticky lg:top-6 dark:border-neutral-700">
                    <p class="mb-2 text-sm font-semibold">{{ __('En esta página') }}</p>
                    @foreach ($sections as $anchor => $label)
                        <a
                            href="#{{ $anchor }}"
                            class="rounded-md px-2 py-1 text-sm text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-700 dark:hover:text-white"
                        >
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </nav>
        </div>
    </div>
</x-layouts::app>
