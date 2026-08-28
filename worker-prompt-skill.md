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

## Tests

Do not create a new test merely to prove prompt compliance or exact CSS/Blade structure. Run only checks justified by the actual risk. Browser presentation is reviewed in the browser, not frozen through source-string tests.

## Handoff

Keep the handoff short:

- branch;
- base SHA;
- head SHA;
- changed files;
- exact changes made;
- checks run;
- unresolved ambiguity/blocker.

No implementation diary.
