# ADR-0002: Hosting Cost Baseline

- Status: Accepted
- Date: 2026-08-15
- Owner: repository owner
- Scope: issue #5 hosting/runtime cost baseline

## Current baseline

The audited production baseline is Scaleway `dev-play-1` (`DEV1-S`) in `AMS1`,
with 2 vCPU, 2 GB RAM and 50 GB of block storage. The current host remains the
working production baseline. Public list prices are planning estimates only;
they do not establish or infer the actual invoice for this host.

## Cost comparison

Pricing was checked against the providers' official sources on 2026-08-15.
Amounts below are approximate monthly prices before tax, using 730 hours/month,
and exclude backups, traffic beyond included allowances, and other optional
services.

### Scaleway DEV1-S (AMS1)

- Compute: 2 vCPU, 2 GB RAM, approximately €6.55/month at the published
  €0.00898/hour rate.
- Storage: the audited 50 GB block-storage requirement is separate. At the
  published €0.000130/GB/hour for Block Storage 5K, this is approximately
  €4.75/month.
- Public IP: a flexible IPv4 is separately priced at €0.004/hour, or about
  €2.92/month when continuously attached. IPv6 and egress are included in the
  instance list price.
- Planning total: approximately €14.22/month for compute, 50 GB block storage
  and one public IPv4.
- Non-equivalent characteristics: the DEV1-S also has its provider-specific
  bandwidth and storage attachment model; the audited Ubuntu host, existing
  address, operational history and recovery material are not represented by
  this list-price total.
- Migration/setup: none while retained; modernization can proceed without a
  provider cutover.

### Hetzner CX23 (Nuremberg/Falkenstein class)

- Compute: 2 vCPU, 4 GB RAM, 40 GB local SSD, approximately €5.49/month at
  the current post-15-June-2026 list price.
- Storage: 40 GB is included. To approximate the audited 50 GB requirement,
  the minimum 10 GB network volume adds about €0.44/month at €0.044/GB/month.
- Public IP: a primary IPv4 adds €0.50/month; primary IPv6 is free.
- Planning total: approximately €6.43/month for CX23, a 10 GB volume and one
  primary IPv4.
- Non-equivalent characteristics: this has twice the RAM, different CPU
  generations/virtualization and local-plus-networked storage semantics, and
  different traffic, availability-zone and provider-operational characteristics;
  it is not a like-for-like replacement for the current host.
- Migration/setup: a replacement would require provisioning, hardening,
  TLS/DNS changes, deployment design, data restore and release validation,
  with a temporary cutover window and operational risk.

### Assumptions and limitations

The comparison uses current public list prices, not an account invoice or
discounts. Prices, availability, tax treatment, IP policy, storage tier and
included traffic can change; Scaleway pricing can vary by availability zone.
The comparison excludes backup, monitoring, support, domain, data-transfer
overages and migration labour. Actual combined resource needs are unknown until
the application, Matomo and intentionally co-hosted services are measured.

One-time migration cost is assumed to be engineering and release-validation
time rather than a provider setup fee. The current baseline has no migration
cost. No replacement should be selected solely from the nominal difference in
these estimates.

## Software/runtime cost

The selected application and runtime components should not require mandatory
commercial runtime, plugin or SaaS dependencies. Self-hosted and open-source
components remain preferred where practical; operational hosting costs still
require explicit justification.

## Decision

Keep the existing Scaleway production host during modernization. Do not migrate
provider merely to obtain a small nominal monthly saving, and do not downsize on
the basis of current legacy utilization. Capacity must account for the future
combined workload: the new moeller-lars application, Matomo and any services
intentionally co-hosted on the host.

Reconsider provider or size only after representative combined production load
is measurable, or if pricing, capacity or reliability changes materially.

## Review triggers

- target runtime and deployment topology are finalized;
- Matomo's resource profile is measured;
- combined host memory, CPU or storage pressure becomes material;
- provider pricing changes materially; or
- reliability or security requirements exceed current host capabilities.

### Official pricing references

- [Scaleway Virtual Instances pricing](https://www.scaleway.com/en/pricing/virtual-instances/)
- [Scaleway Storage pricing](https://www.scaleway.com/en/pricing/storage/)
- [Hetzner Cloud price adjustment (effective 15 June 2026)](https://docs.hetzner.com/general/infrastructure-and-availability/price-adjustment/)
- [Hetzner IPv4 pricing](https://docs.hetzner.com/general/infrastructure-and-availability/ipv4-pricing/)
- [Hetzner Cloud volumes](https://www.hetzner.com/cloud/?country=en)
