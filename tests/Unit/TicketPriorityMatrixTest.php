<?php

use App\Enums\Level;
use App\Enums\TicketPriority;

test('the priority matrix maps every importance and urgency pair', function (Level $importance, Level $urgency, TicketPriority $expected) {
    expect(TicketPriority::fromMatrix($importance, $urgency))->toBe($expected);
})->with([
    [Level::High, Level::High, TicketPriority::Critical],
    [Level::High, Level::Medium, TicketPriority::High],
    [Level::High, Level::Low, TicketPriority::Medium],
    [Level::Medium, Level::High, TicketPriority::High],
    [Level::Medium, Level::Medium, TicketPriority::Medium],
    [Level::Medium, Level::Low, TicketPriority::Low],
    [Level::Low, Level::High, TicketPriority::Medium],
    [Level::Low, Level::Medium, TicketPriority::Low],
    [Level::Low, Level::Low, TicketPriority::Low],
]);

test('the data migration uses the same matrix and level ranges as the application', function () {
    $migration = require __DIR__.'/../../database/migrations/2026_10_01_122440_convert_ticket_scores_to_priority_matrix.php';
    $reflection = new ReflectionClass($migration);

    foreach ($reflection->getConstant('PRIORITY_MATRIX') as $importance => $row) {
        foreach ($row as $urgency => $priority) {
            expect($priority)->toBe(TicketPriority::fromMatrix(Level::from($importance), Level::from($urgency))->value);
        }
    }

    expect($reflection->getConstant('LEVEL_RANGES'))->toBe([
        1 => [1, 3],
        2 => [4, 6],
        3 => [7, 10],
    ]);
});
