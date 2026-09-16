<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    app()->setLocale('es');
});

test('every validation key laravel ships has a spanish translation', function () {
    $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
    $spanish = require lang_path('es/validation.php');

    $missing = [];

    foreach ($english as $key => $message) {
        if (in_array($key, ['custom', 'attributes'], true)) {
            continue;
        }

        if (! array_key_exists($key, $spanish)) {
            $missing[] = $key;

            continue;
        }

        if (! is_array($message)) {
            continue;
        }

        foreach (array_keys($message) as $variant) {
            if (! isset($spanish[$key][$variant])) {
                $missing[] = "{$key}.{$variant}";
            }
        }
    }

    expect($missing)->toBe([]);
});

test('password rule failures render in spanish instead of raw translation keys', function () {
    $validator = Validator::make(
        ['password' => 'abc'],
        ['password' => Password::min(12)->mixedCase()->letters()->numbers()->symbols()],
    );

    expect($validator->fails())->toBeTrue();

    $messages = $validator->errors()->get('password');

    expect($messages)->not->toBeEmpty();

    foreach ($messages as $message) {
        expect($message)->not->toContain('validation.');
    }
});

test('the password attribute name is translated in every message', function (string $value, string $rule, string $expected) {
    $validator = Validator::make(['password' => $value], ['password' => $rule]);

    expect($validator->errors()->first('password'))->toBe($expected);
})->with([
    'min' => ['abc', 'min:12', 'El campo contraseña debe tener al menos 12 caracteres.'],
    'required' => ['', 'required', 'El campo contraseña es obligatorio.'],
    'confirmed' => ['abc', 'confirmed', 'La confirmación de contraseña no coincide.'],
]);

test('common validation rules render in spanish', function () {
    $validator = Validator::make(
        ['name' => '', 'email' => 'no-es-un-email'],
        ['name' => 'required', 'email' => 'email'],
    );

    expect($validator->errors()->first('name'))->toBe('El campo nombre es obligatorio.')
        ->and($validator->errors()->first('email'))->toBe('El campo correo electrónico debe ser una dirección de correo válida.');
});
