# Documentation

The Lars Möller website is one Laravel application with a public site, an editorial administration and a publication/history system. This guide is for **reading and understanding the application**. It is not a log of coding sessions or a list of every class.

## Understand the project

Start with three complementary views:

1. **[Project charter](PROJECT-CHARTER.md)** — what the artist-facing website is for, editorial priorities and product boundaries.
2. **[System architecture](ARCHITECTURE.md)** — how Public, Admin, Media, Publication and the database interact; the most useful first technical read.
3. **[Data model](DATA-MODEL.md)** — the meaning of persistent records and the Working/Committed boundary.

For a concrete change, follow the topic below to **one owning document**, then inspect the implementation. Some detailed references describe older source snapshots; they do not override current `dev` or the user's latest decision.

## Product and editorial behavior

| Question | Read |
| --- | --- |
| How do navigation, Home, Galleries, Artworks, Journals and pages appear publicly? | [Public website](public/site.md) |
| What may become publicly visible? What about image rights, legal text and privacy? | [Rights and privacy](public/rights-privacy.md) |
| How are reusable images, videos, audio, references, variants and deletion handled? | [Media](media.md) |
| How are exhibition venues, organizers, dates, visibility and maps edited? | [Exhibition editorial](content/exhibitions.md) |
| How do the shared editor, embedded images, formatting and safe rendering work? | [Rich Text](content/rich-text.md) |

## Artist administration

Start with **[Admin workspace design](admin/ui.md)** for the overall interaction vocabulary. Then choose the component or responsibility being changed:

| Responsibility | Owner |
| --- | --- |
| Inputs, picker controls and shared styling | [Controls](admin/controls.md) |
| Tabular views, actions, selection, ordering and pagination | [Tables](admin/tables.md) |
| Form dialogs, focus, edit lifecycle and scroll behavior | [Dialogs](admin/dialogs.md) |
| Shell sizing, toolbars, grids, compact states and responsive geometry | [Responsive composition](admin/responsive.md) |
| Working changes, Activity, Undo, audit and publication | [Activity and publication](admin/activity-publication.md) |
| Notification delivery, messaging and Dashboard inbox | [Notifications](admin/notifications.md) |
| Authentication, passwords, account recovery and account security | [Authentication](admin/authentication.md) |
| Responsiveness and performance budgets; diagnosing slow interactions | [Performance and profiling](admin/performance.md) |
| Browser-based interaction review, source cleanup and acceptance | [Browser review](admin/browser-review.md) |

A page may depend on several of these contracts, but each **shared rule** has only one implementation owner. The Admin rules do not control the independent visual design of the public site.

## Running, measuring and releasing

| Topic | Read |
| --- | --- |
| Application tests and safe isolated validation | [Testing](operations/testing.md) |
| Release qualification, snapshots, migrations and cutover | [Release](operations/release.md) |
| Application versus external infrastructure responsibilities | [Server boundary](operations/server-boundary.md) |
| Privacy-conscious analytics and reporting | [Analytics](operations/analytics.md) |
| Internal Laravel Pulse metrics and report export | [Pulse](operations/pulse.md) |

**Migration and pre-release history:** [Plan](migration/plan.md), [invariants](migration/invariants.md), [source inventory](migration/source-inventory.md) and [legacy public behavior](migration/legacy-public.md) exist for concrete cutover questions. They are **not** a second description of today's application.

## Technical reference and source evidence

The [engineering evidence index](architecture/README.md) is for tracing a specific implementation boundary: Admin mutation paths, Public navigation, media cleanup, database relations, generated source inventories and historical investigations. Read it **when you have a technical question**, not as a prerequisite to understanding the product. [Coverage and evidence classification](architecture/completion-criteria.md) distinguishes extracted source, written tests, executed checks and unverified runtime/artist acceptance.

Generated inventories may reflect an older pinned revision. Regenerate retained artifacts **only at the authorized final Docs integration gate** after reconciling the frozen source. Do not interpret diagrams or declarations as executed runtime evidence.

## Contributing to documentation

A good document answers a reader's question, describes how parts fit together and links to the code where necessary. Prefer updating, consolidating, renaming or **removing** a document over accumulating adjacent manuals. Do not preserve aliases for deleted pre-release documents. Never put private hosting configuration, access details, prices or credentials in this public repository.

[Docs branch instructions](../AGENTS.md), the [Docs skill and maintenance method](../.agents/skills/docs/SKILL.md) and the [branch-local worker protocol](../.agents/README.md) describe how agents identify the right owner, verify current code and make safely reviewable changes. They do **not** replace the product documentation above. General Docs and Test ownership is separate from everyday `dev` implementation.

[Security policy](../SECURITY.md) and [contribution guidelines](../CONTRIBUTING.md) remain the repository policy sources. Decisions about release and repository-history cleanup are owned by the [release contract](operations/release.md), not hidden in temporary worker records.
