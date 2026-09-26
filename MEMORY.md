# Mailchimp API Memory
## Scope
- Package role: Communication (SDKs)
- Purpose: This package operates within the Communication (SDKs) layer of the APIs Hub SaaS hierarchy, providing Mailchimp API client functionality.
- Dependency stance: Consumes `anibalealvarezs/api-client-skeleton` and serves downstream Mailchimp integrations and future drivers (`mailchimp-hub-driver`).
## Local working rules
- Consult `AGENTS.md` first for package-specific instructions.
- Use this `MEMORY.md` for repository-specific decisions, learnings, and follow-up notes.
- Use `D:\laragon\www\_shared\AGENTS.md` and `D:\laragon\www\_shared\MEMORY.md` for cross-repository protocols and workspace-wide learnings.
- Keep secrets, credentials, tokens, and private endpoints out of this file.
## Current notes
- Mailchimp client remains a reusable source-facing SDK.
- In `v1.17.0` upgrade, `MarketingApi` supports dual authentication: standard API Key (HTTP Basic) and OAuth 2.0 access token (HTTP Bearer).
- Added full campaign reporting methods: `getEmailActivity()`, `getAllEmailActivityAndProcess()`, `getOpenDetails()` (with Apple MPP `proxy_open` flags), `getClickDetails()`, `getClickMembers()`, `getAllClickMembersAndProcess()`, `getSentToMembers()`, and `getUnsubscribedMembers()`.
- Added connected eCommerce methods: `getEcommerceStores()`, `getEcommerceOrders()`, `getAllEcommerceOrdersAndProcess()`, and `getEcommerceCustomers()`.
- Added organizational taxonomy methods: `getCampaignFolders()` and `getTemplates()`.
- Fixed pagination loop termination in `getAll*AndProcess()` methods by parameterizing `$batchSize`.
- Harmonized with Google and Facebook SDK patterns by providing matching `getAll*` (aggregate array collector with `loopLimit`) and `getAll*AndProcess` (streamed callback processor) pairs across all resources.