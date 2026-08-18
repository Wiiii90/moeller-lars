# Contributing

`moeller-lars` is a focused application rebuild rather than a general website framework. Changes should preserve the product and ownership boundaries documented in [`docs/`](docs/README.md).

## Before changing code

1. Check the relevant GitHub Issue/milestone for acceptance requirements.
2. Read the canonical contract for the subsystem being changed.
3. Keep application responsibilities inside this repository and production-platform responsibilities in `Wiiii90/server-platform`.
4. Never use production credentials/data/media as development fixtures.

## Development workflow

Use the repository-local environment and verification commands in [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md).

Prefer a focused branch and a pull request that explains:

- what changed;
- why the change is required;
- which issue/contract it satisfies;
- security/data/migration implications;
- verification performed.

## Architectural constraints

Changes must not casually reintroduce patterns explicitly removed by the target architecture:

- hard-coded artwork category identities in runtime behaviour;
- legacy PHP/query routing as a target dependency;
- raw HTML or unsafe-link rendering for editorial content;
- public/admin mutations without server-side authorization;
- media handling outside the validated ingest/delivery boundary;
- production Compose/Caddy/host-path/secrets/backup orchestration in this repository;
- raw human analytics duplicated into the application database;
- silent fallback behaviour that hides violated publication/order/media invariants.

## Documentation

When a change modifies a contract, configuration variable, ownership boundary or release requirement, update the canonical document in the same pull request.

Do not create a second document that contradicts an existing canonical contract. Established document paths are intentionally stable; use `docs/README.md` to classify them instead of renaming them without a strong reason.

## Verification

Run targeted checks while iterating. Before an integration checkpoint, run the full verification suite:

```sh
composer test
composer analyse
vendor/bin/pint --test
npm run test:js
npm run build
```

CI remains the final repository-level verification gate for accepted changes.

## Sensitive information

Do not commit or paste into public issues/pull requests:

- passwords, API tokens, private keys or session material;
- production `.env` values;
- database dumps;
- production/private media archives;
- private backup locations/hashes;
- server screenshots or logs containing credentials or unnecessary personal data.

Security-sensitive implementation details should be reduced to the minimum needed for review without exposing operational secrets.
