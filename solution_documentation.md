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

## Task 3 — Handling a very large file

Two separate problems needed solving: the transport and the pipeline.

**Transport:** The original approach base64-encoded the entire file into a single GraphQL variable, requiring the whole file in memory on both the browser and PHP sides. Rather than introducing a separate REST endpoint — which would add a second API paradigm to a codebase built entirely around GraphQL — the client now reads the file as text, splits the data rows into chunks of 5 000, and fires sequential `importCsv` mutations, one per chunk. Each PHP request only processes 5 000 rows at a time. Results are accumulated client-side and skipped-row line numbers are offset so they refer to the original file rather than the chunk. A progress indicator shows the current chunk during the import.

**Pipeline:** `CsvReader::read()` previously accumulated every row into a PHP array before returning, holding the entire file in memory at once. It now returns a `\Generator` that yields one row at a time. The `foreach` loop in `ImportRunner` is iterable over a generator without any structural change, so peak memory stays O(1) regardless of chunk size.

Switching to a generator also let the two-loop structure in `ImportRunner` (a separate normalisation pass then a processing pass) be collapsed into a single loop with inline normalisation. This eliminates the foreach-by-reference pattern entirely — the class of bug fixed in Task 1 cannot occur here.

**Performance:** With large files two further bottlenecks emerged. First, each row previously cost two SQL round-trips: a `SELECT` (`existsByHrId`) followed by a separate `INSERT` or `UPDATE`. This was replaced with a single `INSERT … ON CONFLICT(hr_id) DO UPDATE SET …` statement, reducing that to one query per row. The existing hr_ids are pre-loaded once at the start of each chunk into a PHP hash so insert vs. update is determined in O(1) without any per-row SELECT. Second, SQLite commits to disk after every individual write by default. Wrapping each chunk in a transaction means the disk is flushed only once per chunk, which can be 50–100× faster in practice.

**Read-side pagination:** Loading all users in a single query caused an out-of-memory crash on the Users page after a large import. The `users` query now accepts `limit` and `offset` arguments (defaults: 100 / 0). `UserRepository::findAll()` passes them through to a `LIMIT … OFFSET …` query, a new `usersCount` query provides the total for calculating pages, and the Users page shows Previous / Next controls. PHP never holds more than 100 mapped rows in memory regardless of how many users are in the store.

**Remaining ceilings:** The `file.text()` call in the browser loads the full file into JavaScript memory before chunking — for files in the GB range a `ReadableStream` approach would be needed on the client side too. PHP's `upload_max_filesize` / `post_max_size` ini limits (default 8 MB) do not apply to the GraphQL JSON body but would need to be considered if the transport were ever changed to multipart upload.

---

## Anything else

With more time: client-side email validation before firing the mutation, a per-row saving indicator, and debouncing the Save button to prevent double-submits.
