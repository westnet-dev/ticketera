<?php

use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('role:admin still only allows admins on an admin route', function () {
    $client = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($client)->get(route('admin.tickets'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.tickets'))->assertOk();
});

test('role:client,admin allows both a client and an admin on a shared route', function () {
    $client = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($client)->get(route('ticket.index'))->assertOk();
    $this->actingAs($admin)->get(route('ticket.index'))->assertOk();
});

test('the middleware allows a user matching any of several roles', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    $result = (new RoleMiddleware)->handle(Request::create('/'), fn ($request) => response('next-called'), 'client', 'admin');

    expect($result->getContent())->toBe('next-called');
});

test('the middleware blocks a user matching none of the given roles', function () {
    $client = User::factory()->create();

    $this->actingAs($client);

    (new RoleMiddleware)->handle(Request::create('/'), fn ($request) => response('next-called'), 'admin');
})->throws(HttpException::class);
