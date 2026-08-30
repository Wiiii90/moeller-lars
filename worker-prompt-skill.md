# Worker prompt skill

Use this file when an orchestrator creates a coding-worker prompt.

The worker is an **executing hand**, not a product designer or co-orchestrator. The orchestrator decides the implementation direction before delegation.

## Prompt size

Default worker prompts should be **20–60 lines**. Treat roughly **100 lines as a hard ceiling** unless the user explicitly asks for a larger handoff.

Do not paste long project history, UI style essays, architecture summaries or repeated exclusions into the prompt. Point to `AGENTS.md` and `ui-skills.md` instead.

## Required prompt shape

A worker prompt should normally contain only:

1. repository + exact base SHA + worker branch name;
2. files/components to inspect or edit;
3. the exact changes to make;
4. a short `DO NOT CHANGE` list for this task;
5. the few checks actually required;
6. commit/push instruction;
7. short handoff fields.

Deliver the whole worker prompt in **one contiguous fenced code block**.

## No autonomous product decisions

Do not delegate decisions that the orchestrator can make first.

Avoid instructions such as:

- improve/harmonize as needed;
- make it more consistent;
- choose the best layout;
- refactor where appropriate;
- clean up related UI;
- adjust anything else that looks wrong.

Instead specify the result directly, for example:

- `Settings` stays below the image;
- remove the separator above the table;
- do not add a bottom table border;
- keep `Add CV entry` in the existing accepted footer position;
- preserve the existing shared refactor outside the named selectors.

A direct user decision is binding. The worker must not reinterpret it through generic consistency, style or best-practice arguments.

## Ambiguity rule

If implementation exposes a genuine product/UI choice that is not specified:

- do **not** invent a choice;
- leave that part unchanged when safe;
- report the ambiguity in the handoff.

The worker may choose low-level implementation mechanics only when they do not alter product behavior or visible presentation beyond the exact requested change.

## Scope discipline

Do not broaden the task because nearby code could also be cleaned up. Do not perform discretionary centralization, redesign, renaming, dependency changes, test-suite cleanup or architecture work unless the prompt explicitly requests it.

For shared CSS/components, name the intended consumers and explicitly state whether other consumers must remain visually unchanged.

Remote workers normally do not access the user's `P:\moeller-lars` checkout. Do not make that path a remote-worker prerequisite. Remote source work and user-executed local browser/runtime PowerShell are separate phases.

## Visual repair evidence

A visual worker may make source changes, but it cannot establish browser acceptance from source alone.

For a first repair pass, the prompt must name the exact current reference surface at the exact base SHA when the user says a target should look like another existing element. Do not substitute an older branch or historical accepted commit unless the user explicitly asks to restore that historical presentation.

If the **same visible defect survives one worker + browser round**, stop issuing another normal implementation prompt. Treat it as a repeat failure. Before another worker is sent, the orchestrator must establish the actual difference using the current candidate and reference, for example:

- rendered DOM / parent context;
- computed styles;
- element geometry and spacing;
- browser screenshot evidence;
- runtime state when the defect is behavioral rather than purely visual.

The next worker receives the proven difference, not another prose instruction such as “make it match”.

When two controls are intended to be truly identical, prefer one complete shared consumer/component that owns the relevant wrapper and control geometry. Reusing only a leaf class such as `admin-action` is not proof that the rendered result is identical.

A visual handoff may state `source coherent` or `ready for browser verification`. It must not state or imply `visual fix verified`, `browser accepted` or equivalent unless the current built candidate was actually inspected in the browser.

## Tests

Workers may use temporary tests as implementation scaffolding when that helps them reason safely. Temporary scaffolding is **not automatically repository test coverage**.

Every new or materially rewritten test must be classified before commit/handoff:

### DURABLE

Keep the test only when it protects a long-lived product/domain/security/data-integrity invariant, for example:

- persistence semantics;
- authorization/security;
- validation;
- destructive-operation guards;
- publication/Working/Committed behavior;
- media lifecycle/reference safety;
- migration/data invariants;
- user-observable functional behavior.

A useful durability check is: **if the implementation were replaced tomorrow while preserving the same product behavior, should this test still be valid?** If yes, it may be durable.

### SCAFFOLDING

Delete before commit/handoff when the test mainly helped the worker satisfy the current prompt or chosen implementation, for example:

- `file_get_contents()` tests against PHP/Blade/CSS source;
- `toContain()` / regex checks for CSS classes, wrapper names, selector ownership or file placement;
- tests named after browser-repair rounds, reconciliation rounds, branch/candidate chronology or worker tasks;
- tests whose only value is proving that an old class/string disappeared or a new class/string exists;
- tests that freeze unaccepted presentation markup.

Do not create a new persistent test merely to prove prompt compliance or exact CSS/Blade structure. Browser presentation is reviewed in the browser, not frozen through source-string tests.

If a rare source-level test protects a genuine durable security/build/architecture invariant that cannot be tested more directly, the handoff must explicitly justify why it is durable.

Run only checks justified by the actual risk. Do not broaden a tiny browser repair into a long test cycle merely because a large suite exists.

## Handoff

Keep the handoff short:

- branch;
- base SHA;
- head SHA;
- changed files;
- exact changes made;
- checks run;
- durable tests added/changed, if any;
- scaffolding tests removed before commit, if any;
- unresolved ambiguity/blocker.

No implementation diary.
