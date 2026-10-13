# Scheduler & Mutex — reference implementation

A runnable PHP 8.3 implementation of the `Scheduler` and `Mutex` contracts from
the design document (`Scheduler and Mutex`). It exists to prove the design holds
up in code and to give me something concrete to walk through on the follow-up
call.

The brief: integrate a task scheduler (Laravel Scheduler now, with crunz as an
open option) and replace Laravel's unreliable mutex with our own — without
binding the system to any of them. So the whole thing is built against
interfaces, and Laravel/crunz/Redis are just swappable implementations behind
them.

---

## The one idea

**The application owns the parts that encode business decisions; the third-party
library owns only the mechanical parts.**

| Owned by the application (these interfaces)                              | Contributed by the scheduler library |
| ------------------------------------------------------------------------ | ------------------------------------ |
| Task declaration (`ScheduledTask`, `TaskDefinition`)                      | Cron evaluation                      |
| What runs once per fleet vs per server (`LockPolicy`, the `Mutex`)        | Process spawning                     |
| The result model and exit code (`ScheduleRunReport`, `TaskResult`)        | Timezone handling                    |
| The CLI entry point (`ScheduleRunner`)                                    |                                      |
| Persistence of locks (`LockStore` drivers)                               |                                      |

Everything in the left column is ours and survives a library swap untouched.
Swapping Laravel Scheduler for crunz means writing one new `ScheduleEngine`
implementation — nothing else moves. This repo ships **two** engines
(`NativeScheduleEngine` and `CrunzScheduleEngine`) precisely to demonstrate that.

---

## Run it

```bash
composer install          # pulls PHPUnit + dragonmantank/cron-expression (dev)

# List the demo schedule
php bin/scheduler list

# Run a tick for a given instant (deterministic — uses a frozen clock)
php bin/scheduler tick --now="2026-10-13 01:00"

# Same tick through the crunz-style engine instead of the native one
php bin/scheduler tick --now="2026-10-13 01:00" --engine=crunz

# Tests
composer test
```

`--now` is the demo trick: it freezes the clock so you can replay any minute and
see exactly which tasks fire. In production the cron adapter just runs
`php bin/scheduler tick` every minute with the real clock.

---

## Architecture

### Scheduler — the tick pipeline

```
cron (* * * * *) on every node
        │
        ▼
ScheduleRunner::tick()
        │
   LockingScheduleRunner ── tick lock held elsewhere? ──► skippedTick()  (exit 0)
        │  (one node wins the minute)
        ▼
DispatchingScheduleRunner
        │  provider.provide()  → Schedule (built in memory, from code)
        ▼
ScheduleEngine::dispatchDue(schedule, now)
        │  for each task: due in its own timezone?
        ▼
LockingTaskRunner ── per-task lock held? ──► TaskResult::skipped
        │  run via TaskExecutor (shell / console / handler), release in finally
        ▼
ScheduleRunReport → CLI exit code
```

Two decorated runners, deliberately separate:

- **`DispatchingScheduleRunner`** — the actual work: build the schedule, ask the
  engine to dispatch what's due. No locking.
- **`LockingScheduleRunner`** — wraps it with the per-minute **tick lock** so
  that, across a fleet where cron fires on every node, exactly one node runs the
  tick and the rest exit immediately.

### Mutex — the lock stack

```
application code
        │  Mutex::tryAcquire(key, options) → ?LockHandle
        ▼
StoreMutex            mints a fresh LockOwner, owns retry/wait policy
        │
        ▼
LockStore (@internal) acquire / extend / release — each ONE atomic operation
        │
        ▼
InMemoryLockStore  (or PdoLockStore / RedisLockStore / MemcachedLockStore)
```

Application code only ever sees `Mutex` and `LockHandle`. `LockStore`,
`LockOwner` and the owner token are `@internal` — they never leak out.

The four properties of a correct distributed lock, and where each is enforced:

| Property       | Enforced by                                                        |
| -------------- | ------------------------------------------------------------------ |
| Owner token    | `LockOwner` argument on every `LockStore` method; token lives inside `LockHandle` |
| Atomic CAS     | the "single storage operation" rule on every `LockStore` method    |
| TTL            | `Duration` argument on `acquire()` / `extend()`                     |
| Extend         | `LockHandle::refresh()` → `LockStore::extend()`                     |

---

## Decisions I want to defend

These are the choices the design turns on — the interesting part of the call.

**1. `LockStore` has no `get()`.** If a driver could read the current owner and
then decide, every driver would be tempted to do check-then-set in PHP and
reintroduce exactly the race an unreliable mutex gets wrong. Removing the read
removes the temptation: `acquire`/`extend`/`release` each carry their own
condition and map to one atomic backend operation (Redis `SET NX PX` / `EVAL`,
one conditional SQL `UPDATE`, memcached `add`/`cas`).

**2. `tryAcquire()` returns `?LockHandle`; `acquire()` throws.** A held
tick/task lock is normal control flow for the scheduler — "someone else has it,
skip" — not an error. So the scheduler only ever calls `tryAcquire()` and a
`null` means skip. `acquire()` exists for callers who genuinely cannot proceed
without the lock.

**3. `release()` is best-effort and idempotent; the incident signal lives in
`refresh()`.** This is the one place the implementation knowingly diverges from
the document. The PDF says `release()` should throw `LockLost` *only* when a
different owner now holds the key, but stay silent on expiry. With no `get()`,
`LockStore::release()` returns a single `false` for **both** "expired, nobody
holds it" and "a different owner holds it now" — they are indistinguishable
without a read. Rather than add a read (and reopen decision #1), `release()`
swallows the `false` (idempotent cleanup) and the overrun signal is carried by
`refresh()` instead, where a `false` unambiguously means "your TTL passed, you
lost the lock, you may now be running concurrently — abort." I think that's the
right trade; it's a genuine tension in the spec and worth discussing.

**4. The tick lock is never released — it's left to expire.** If
`LockingScheduleRunner` released the minute's lock when it finished, a second,
slightly-late node could grab the same minute and double-run the tick. Letting
it expire keeps the minute "taken" until it no longer matters. (Per-task locks
*are* released in a `finally` — different purpose: they gate a single run, not a
window.)

**5. `Clock` is injected, not `Time::now()`.** The document wrote `Time::now()`
as a static facade. Here it's an injected `Clock` port, so tests freeze time and
assert "a task due at 03:00 fires at 03:00" and "a lock whose TTL passed is free
again" without sleeping. `FrozenClock` also powers the CLI `--now` flag.

**6. Due-ness is evaluated per task, in the task's own timezone.** The clock
stays in UTC; each task converts at the edge (`now->setTimezone(task.timezone)`).
That's why `reports.send` (`0 3 * * *` Europe/Prague) fires at 01:00 UTC.

**7. Definitions are data; execution is one class.** `ShellTask` / `ConsoleTask`
/ `HandlerTask` only *describe* work. All the "how to run it" logic — process
spawning, timeout enforcement, container resolution — lives in `NativeTaskExecutor`,
so the definition types stay trivial and serialisable and there's one place that
knows about processes. Caveat: a hard, kill-able timeout only applies to process
tasks; a `HandlerTask` runs in-process and PHP can't pre-empt its own call stack,
so its timeout is advisory. Long kill-able work should be a `ConsoleTask`.

**8. `LockingTaskRunner` is shared by both engines.** The only thing that differs
between `NativeScheduleEngine` and `CrunzScheduleEngine` is *how due-ness is
decided*. Locking, timeout mapping and the result model are identical, so they
live in one shared runner — the engines are thin.

**9. Duplicate ids are caught once, at the merge point.** `Schedule::fromTasks()`
throws `DuplicateTaskId`, and `CompositeScheduleProvider` is the single place
every module's tasks are merged — so a cross-module id clash fails loudly at
wiring time instead of silently shadowing a task at run time.

---

## Swapping implementations

**A different scheduler library** → implement `ScheduleEngine` (see
`CrunzScheduleEngine`). Reuse `LockingTaskRunner`; only re-implement `isDue`.

**A different lock backend** → implement the three-method `LockStore` (see
`InMemoryLockStore`). Nothing above the interface changes. Sketches:

- `PdoLockStore` (shared MySQL) — one conditional `UPDATE ... WHERE owner = ? OR expires_at < NOW()`. No new infrastructure, survives restarts.
- `RedisLockStore` — `SET key owner NX PX ttl` for acquire; a tiny Lua script for owner-checked extend/release.
- `MemcachedLockStore` — `add(key, owner, ttl)` for acquire; `cas` for the rest.

---

## Layout

```
src/Common/      Duration, Clock (SystemClock, FrozenClock)
src/Mutex/       Mutex, LockHandle, LockKey, LockOptions, LockOwner,
                 StoreMutex, StoreLockHandle, Store/{LockStore, InMemoryLockStore}
src/Scheduler/   runners, ScheduleEngine (+ Native), providers, Schedule,
                 value objects, CronExpression, Definition/, Execution/,
                 Adapter/Crunz/CrunzScheduleEngine
demo/            example schedule + handlers (the PDF's tasks)
bin/scheduler    the cron adapter / CLI
tests/           49 tests covering lock semantics, cron, locking tick, engine parity
```
