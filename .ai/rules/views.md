---
paths:
  - 'resources/views/**'
---

# Views

## Build pages from the shared UI components
Pages sit on a gray canvas (body bg-zinc-50) with white cards. Use <x-page-header> (title/subtitle + actions slot), <x-panel> (card, optional heading), <x-table-panel> around every flux:table (applies uppercase xs column headings), <x-stat-card>, <x-tabs>/<x-tab> (underline tabs with count; href → link, otherwise button for wire:click), <x-empty-state>, <x-meter>, <x-user-cell>. Ticket rows use <x-tickets.subject-cell>, <x-tickets.priority-indicator> (Ticket::priorityLabel buckets 1–10 into Alta ≥7 / Media ≥4 / Baja) and a muted "#TK-{id}" ID cell. Don't hand-roll bordered divs for these.
