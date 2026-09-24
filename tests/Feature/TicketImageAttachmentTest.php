<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

/**
 * @return array<int, UploadedFile>
 */
function fakeTicketImages(int $count): array
{
    return collect(range(1, $count))
        ->map(fn (int $i) => UploadedFile::fake()->image("evidencia-{$i}.jpg"))
        ->all();
}

test('a ticket can be created with the maximum of 5 images', function () {
    $client = User::factory()->create();

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Cinco capturas del problema')
        ->set('description', 'Adjunto todas las capturas que pude tomar del error.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', fakeTicketImages(5))
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::firstOrFail()->images()->count())->toBe(5);
});

test('a ticket cannot be created with more than 5 images', function () {
    $client = User::factory()->create();

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Seis capturas del problema')
        ->set('description', 'Adjunto más capturas de las permitidas por el sistema.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', fakeTicketImages(6))
        ->call('save')
        ->assertHasErrors(['images']);

    expect(Ticket::count())->toBe(0);
});

test('a non image file cannot be attached to a ticket', function () {
    $client = User::factory()->create();

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Adjunto un archivo que no es imagen')
        ->set('description', 'El sistema solo debería aceptar imágenes como adjunto.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', [UploadedFile::fake()->create('reporte.pdf', 100, 'application/pdf')])
        ->call('save')
        ->assertHasErrors(['images.0']);

    expect(Ticket::count())->toBe(0);
});

test('the images already attached to a draft count toward the 5 image cap when submitting', function () {
    $client = User::factory()->create();
    $draft = Ticket::factory()->for($client)->draft()->create();

    collect(range(1, 4))->each(fn (int $i) => $draft->images()->create(['image_path' => "tickets/existente-{$i}.jpg"]));

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket', ['draft' => $draft])
        ->set('title', 'Borrador con cuatro imágenes previas')
        ->set('description', 'Intento sumar dos imágenes más y superar el tope.')
        ->set('priority', 5)
        ->set('urgency', 5)
        ->set('impact', 5)
        ->set('images', fakeTicketImages(2))
        ->call('submit')
        ->assertHasErrors(['images']);

    expect($draft->refresh()->status)->toBe('draft');
    expect($draft->images()->count())->toBe(4);
});

test('saving a draft cannot push it over the 5 image cap', function () {
    $client = User::factory()->create();
    $draft = Ticket::factory()->for($client)->draft()->create();

    collect(range(1, 5))->each(fn (int $i) => $draft->images()->create(['image_path' => "tickets/existente-{$i}.jpg"]));

    $this->actingAs($client);

    Livewire::test('tickets.create-ticket', ['draft' => $draft])
        ->set('title', 'Borrador que ya llegó al tope')
        ->set('images', fakeTicketImages(1))
        ->call('saveDraft')
        ->assertHasErrors(['images']);

    expect($draft->images()->count())->toBe(5);
});
