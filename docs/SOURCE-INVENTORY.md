# Source inventory

## larsmoeller: current-site reference

- Legacy PHP/MySQL public site with artwork categories, landing artwork, CV text, contact page, and an admin area.
- Connected to a server-hosted Git remote named `live`; the local clone has no active hook, so any automated deployment logic is expected to live on the server.
- The Apache rules currently direct traffic to HTTP, which is incompatible with the HTTPS requirement.
- The source contains security-sensitive legacy patterns. Its credentials and history must stay out of public GitHub until they have been rotated and rewritten.

## glassygallery: useful ideas, not a base

- Separate frontend, admin frontend, API, migrations, media records, user records, and CI/CD documentation.
- Useful inspiration: media metadata, structured pages, role vocabulary, database migrations, Docker/CI awareness.
- Not suitable as the base because the UI is a general website customizer rather than a Lars Möller editorial tool; its analytics screen is a placeholder and several API mutations lack consistent authorization safeguards.

## moeller-lars: target

- Contains only target documentation and later clean implementation.
- No legacy source, data dump, production image archive, or production configuration is copied here.
