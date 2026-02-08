# MC Logs (Blueprint Addon)
Upload Minecraft server logs to mclo.gs and view the results from inside the Pterodactyl panel (via Blueprint).

## What This Addon Does
- Adds a server route (`/mclogs`) so users can open an "MC Logs" page for a server.
- Lists files from the server's `/logs` directory using the Pterodactyl client API.
- Uploads a selected log file to the mclo.gs API and returns a shareable URL.
- Stores upload history in browser `localStorage` per server UUID (so each server keeps its own list).
- Fetches mclo.gs `raw` + `insights` data and renders it in the UI.

## Privacy / Security
Uploading sends the full log text to a third-party service (mclo.gs). Logs can contain IPs, tokens, and other sensitive data. Review and sanitize before uploading.

## Compatibility
- Blueprint Framework on Pterodactyl Panel
- Target: `beta-2024-12` (see `conf.yml`)

## Installation / Development Guides
Follow the official Blueprint guides for installing addons and developing components:
`https://blueprint.zip/guides`

Uninstall (as shown in the admin view):
`blueprint -remove mclogs`

## How It Works (Repo Layout)
- `conf.yml`: Blueprint addon manifest (metadata, target version, entrypoints).
- `components/Components.yml`: Blueprint route injection for the server area (adds the `/mclogs` page).
- `components/LogsPage.tsx`: React page that lists `/logs`, uploads to mclo.gs, and displays results/history.
- `client/wrapper.blade.php`: Loads Font Awesome for UI icons.
- `public/versions/*.png`: Server type icons used when rendering mclo.gs insights.
- `admin/view.blade.php`: Admin page snippet shown in Blueprint's admin UI.

## Customization (Theme/UX)
- Route name/path and component: `components/Components.yml`
- UI styling/layout: `components/LogsPage.tsx` (twin.macro + Tailwind classes)
- Version icon mapping: `getServerImageUrl()` in `components/LogsPage.tsx`

## Contributing
This repo is shared so the community can help improve and extend the addon, not because it's abandoned.
Where it helps, the code includes comments explaining non-obvious behavior; keep comments high-signal.

### Pull Request Requirements
- Clearly state what's been added/updated and why.
- Include images or a short video of it working/in action (especially for UI changes).
- Keep changes focused and avoid unrelated formatting-only churn.
- Keep credits/attribution intact (see `LICENSE`).

## License
Source-available. Redistribution and resale (original or modified) are not permitted, and original credits must be kept within the addon.
See `LICENSE` for the full terms.
