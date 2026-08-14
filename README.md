# moeller-lars

Secure rebuild of the Lars Möller artist website.

## Product rule

Visitors should experience the existing public website: its visual language, routes, artwork order, and core interactions are preserved. The rebuild changes the operational layer, not the artistic presentation.

The artist receives a purpose-built editorial backend for artworks, exhibitions, CV, blog posts, media, and privacy-conscious visitor statistics.

## Repository boundaries

This repository is the future production codebase. It deliberately contains no copied credentials, database dumps, production media, or source files from the earlier projects.

| Repository | Role in the rebuild | Not reused as production code |
| --- | --- | --- |
| `larsmoeller` | visual/behavioural reference, content inventory, deployment evidence | authentication, configuration, direct SQL/PHP admin code |
| `glassygallery` | ideas for media handling, structured data, role concepts, and CI/CD | visual site builder, unfinished admin shell, current authorization/API implementation |
| `moeller-lars` | clean target for implementation, tests, staging, and final deployment | — |

## Start here

- [Project charter](docs/PROJECT-CHARTER.md)
- [Target architecture](docs/ARCHITECTURE.md)
- [Migration plan](docs/MIGRATION-PLAN.md)
- [Source inventory](docs/SOURCE-INVENTORY.md)
- [Sol handoff prompt](docs/SOL-HANDOFF.md)

## Security rule

Do not place secrets in this repository, including in issues, screenshots, sample data, commits, or CI files. Real deployment configuration belongs in the hosting platform's secret store or a server-local `.env` file.
