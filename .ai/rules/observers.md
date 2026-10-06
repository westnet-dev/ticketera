---
paths:
  - app/Observers/TicketObserver.php
---

# Observers

## ticket_history.field is a MySQL ENUM
Adding a field to TicketObserver::WATCHED_FIELDS (or any new TicketHistory `field` value) requires a migration that widens the `ticket_history.field` ENUM via `ALTER TABLE ... MODIFY`; otherwise inserts fail with "Data truncated for column 'field'". In `down()`, delete rows with the new value before narrowing the ENUM. Fields only admins may see in the history go in TicketHistory::ADMIN_ONLY_FIELDS.
