## Task 1 — Fix the broken import

**Root cause:** The normalisation loop iterates by reference (`foreach ($rows as &$row)`). After the loop completes, `$row` remains a live alias to the last element of the array. When the second `foreach` begins, each assignment of `$row` to the current element overwrites `$rows[last]` through that alias. By the time the loop reaches the final row, it contains a duplicate of the second-to-last row and attempts to insert it again — hitting the `UNIQUE` constraint on `hr_id`. The last member of staff never makes it into the store.

**Fix:** Added `unset($row)` between the two loops to break the dangling reference immediately after the normalisation pass.

