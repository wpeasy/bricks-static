# Roadmap

Planned and under-consideration work for **Bricks Static** (Free + Pro). Items
here are not yet scheduled and may change. Pro-facing items are also listed as
`[PLANNED]` at the bottom of `pro/CHANGELOG.md`.

## Under consideration

- **Investigate data attribute replacement.** Revisit a per-destination `data-*`
  value replacer with a narrower, reliable match (the earlier broad approach was
  removed). Likely a Pro replacement panel.
- **JS defer and optimization.** Defer/optimize emitted JavaScript on the static
  output for faster front-end loads.
- **Other sync options, Connection Agent, etc.** Additional deploy/sync targets
  and a connection agent to simplify destination setup and transport.
  The agent is specced as the **Connector transport** — a user-installed,
  per-pairing PHP receiver spoken to over signed HTTPS (chunked package upload,
  no FTP). See `docs/CONNECTOR_PLAN.md`.
- **nginx auto-apply (Tier 2).** Opt-in SSH apply for SFTP destinations
  (phpseclib `SSH2`, fixed `nginx -t && nginx -s reload` behind a user-installed
  sudoers rule). Tier 1 — detect + verify via `Sync\ServerProbe` / "Check server"
  — shipped 2026-09-17.
