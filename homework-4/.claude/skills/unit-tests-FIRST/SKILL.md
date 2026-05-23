---
name: unit-tests-FIRST
description: Defines the FIRST principles for writing good unit tests and a checklist an author applies to confirm a given test satisfies each principle.
---

## Fast

Unit tests must execute in milliseconds. Tests that hit real databases, file systems, or external services over the network introduce wait times that compound across a suite, break the feedback loop, and discourage frequent runs. Isolate the code under test; stub or mock anything that crosses a process boundary.

## Independent

Each test must be able to run in any order and in isolation without relying on state set up by another test. Shared mutable state (a module-level variable, a file on disk, a populated database) causes intermittent failures that are hard to reproduce and impossible to parallelize.

## Repeatable

A test must produce the same result on every run — on any machine, at any time, regardless of network conditions or ambient state. Non-determinism (timestamps, random IDs, environment variables, external services) must be controlled or frozen.

Example, avoid asserting on the exact value of generated tokens or timestamps; assert on shape (e.g. `expect(token).toBeDefined()`) or freeze `Date.now` with a stub when the value matters.

## Self-validating

A test must produce a clear boolean outcome — pass or fail — without a human reading log output or comparing files manually. Every assertion must be explicit; a test that merely runs the code without asserting anything always passes and proves nothing.


## Timely

Tests should be written at the same time as the code they cover — ideally before (TDD) or immediately after. Tests written long after the fact often cover only the happy path because the author no longer remembers the edge cases that existed during implementation.


## FIRST checklist

Run each check against a single test before marking it done:

- [ ] **Fast** — Does the test complete without hitting any real external service, database, or filesystem?
- [ ] **Independent** — Does the test pass if run alone, out of order, or after any other test in the suite?
- [ ] **Repeatable** — Does the test produce the same result on every machine and every run, with no dependency on time, randomness, or environment?
- [ ] **Self-validating** — Does the test contain at least one explicit assertion that will fail if the behaviour is wrong?
- [ ] **Timely** — Was the test written to cover the code that was changed lately, targeting the specific behaviour the fix introduced or corrected?
