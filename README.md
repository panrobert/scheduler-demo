# Scheduler & Mutex — reference implementation

A runnable PHP 8.3 implementation of the `Scheduler` and `Mutex` contracts from
the design doc. Built against interfaces so Laravel Scheduler / crunz / Redis are
swappable implementations, never dependencies.

**The idea:** the application owns the parts that encode decisions — task
declaration, lock policy, the result model, the CLI, lock persistence. The
third-party library contributes only the mechanical parts — cron evaluation,
process spawning, timezones. Swapping Laravel for crunz means writing one new
`ScheduleEngine`; nothing else moves. This repo ships **two** engines to prove it.

## Run it

```bash
composer install
php bin/scheduler list
php bin/scheduler tick --now="2026-10-13 01:00"              # native engine
php bin/scheduler tick --now="2026-10-13 01:00" --engine=crunz
composer test                                               # 49 tests
```

`--now` freezes the clock so you can replay any minute. In production the cron
adapter just runs `php bin/scheduler tick` every minute.

## How a tick flows

```
cron on every node → ScheduleRunner::tick()
  → tick lock (one node wins the minute, rest skip)
  → provider builds the Schedule from code
  → engine dispatches what's due (each task in its own timezone)
  → per-task lock → run (shell/console/handler) → release
  → ScheduleRunReport → exit code
```

The mutex sits behind `Mutex`/`LockHandle`; the `@internal` `LockStore` has just
three atomic methods (`acquire`/`extend`/`release`) and deliberately **no
`get()`** — so no driver can do a racy check-then-set. The owner token lives
inside the handle, so you can only release a lock you hold.

## A few choices worth noting

- **No `get()` on `LockStore`** — forces every operation to be a single atomic
  CAS, which is exactly what an unreliable mutex gets wrong.
- **`release()` is best-effort/idempotent** — with no `get()`, the store returns
  one ambiguous `false` for both "expired" and "stolen", so the overrun incident
  signal lives in `refresh()` instead (where `false` unambiguously means "you
  lost it"). This is the one conscious divergence from the design doc.
- **Tick lock is left to expire, not released** — releasing it would let a late
  node double-run the same minute.
- **`Clock` is injected** (not a `Time::now()` static) — deterministic tests and
  the `--now` flag.
- **Timeouts are hard for process tasks, advisory for in-process handlers** —
  PHP can't pre-empt its own call stack, so long killable work is a `ConsoleTask`.

## Layout

```
src/Common/     Duration, Clock
src/Mutex/      Mutex, LockHandle, LockStore (+ InMemoryLockStore), StoreMutex
src/Scheduler/  runners, ScheduleEngine (native + crunz), value objects,
                CronExpression, Definition/, Execution/
demo/           example schedule + handlers
bin/scheduler   the cron adapter / CLI
tests/          49 tests: lock semantics, cron, locking tick, engine parity
```
