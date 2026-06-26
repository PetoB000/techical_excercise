## Task 1 — Fix the broken import

**Root cause:** The normalisation loop iterates by reference (`foreach ($rows as &$row)`). After the loop completes, `$row` remains a live alias to the last element of the array. When the second `foreach` begins, each assignment of `$row` to the current element silently overwrites `$rows[last]` through that alias. By the time the loop reaches the final row, it contains a duplicate of the second-to-last row and attempts to insert it again — hitting the `UNIQUE` constraint on `hr_id`. The last member of staff never makes it into the store.

**Fix:** Added `unset($row)` between the two loops to break the dangling reference immediately after the normalisation pass. This is the canonical PHP idiom for this pattern, recommended by the PHP manual. One line added, no logic changed.

---

## Task 2 — Support updating users

### 2a — Upsert in the import pipeline

Extended the existing pipeline layers minimally: `ImportSummary` gained an `$updated` counter (`addUpdated()` / `updated()`); `UserRepository` gained `updateByHrId()` (full field overwrite, used by the import) and `updateById()` (sparse update from whichever fields are provided, used by the mutation); `ImportRunner` now checks `existsByHrId()` before deciding between insert and update; and the `ImportCsv` resolver replaced the hardcoded `'updated' => 0` with `$summary->updated()`.

**Trade-off:** The import upsert overwrites all fields rather than merging. This is correct for a nightly HR export where HR is the authoritative source for every attribute. The `existsByHrId()` SELECT-before-write is fine at this scale; at very large volume it would be replaced with a single `INSERT … ON CONFLICT DO UPDATE`.

### 2b — Edit user flow

Added inline editing to the existing `UserTable` component — an Edit button per row that swaps the cells for pre-filled inputs with Save / Cancel. Saving calls the `updateUser` mutation (already declared in the schema; the resolver stub and `UserMapper::inputToColumns()` helper were already in place) and emits an `updated` event to `UsersView`, which patches the reactive list without a full refetch. `hr_id` is read-only in the form as it is the stable HR identifier.

**Trade-off:** Inline editing requires no routing changes and keeps the user in context. A dedicated page or modal would be preferable if the form were to grow (e.g. role assignment). No client-side validation was added; server errors surface inline below the editing row.

---

## Anything else

With more time: client-side email validation before firing the mutation, a per-row saving indicator, and debouncing the Save button to prevent double-submits.
