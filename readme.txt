=== Token Membership ===
Contributors: spntn
Tags: nft, token, membership, web3, blockchain
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gate your content using blockchain token ownership. Users with a membership token see the content — everyone else sees a "Get Membership" button.

== Description ==

**Token Membership** connects your site to the SPNTN Token Membership backend to offer token-based access control.
Instead of passwords or account-based subscriptions, users hold a blockchain NFT that unlocks your content.

All projects share a single deployed contract on Polygon (`0xF912D97BB2fF635c3D432178e46A16930B5Af51A`).
No per-project deployment is required.

= How it works =

1. Install the plugin and activate it. In the SPNTN dashboard, open your project and click **⚡ Generate Setup Code**. Paste the 8-character code in **Settings → Token Membership → Quick Setup** and click **Apply** — all fields fill automatically.
2. Create a project in the SPNTN dashboard.
   - Set a **Creator Wallet** — this wallet receives ~97% of every sale on-chain.
   - Click **Activate Project** to link it to the shared Polygon contract.
3. Copy the Project ID and paste it as `project_id` in the shortcode below.
4. Wrap any content in the shortcode:

`[token_membership project_id="YOUR_PROJECT_ID"]
  Your protected premium content goes here.
[/token_membership]`

5. Visitors without a token see a wallet connect + "Get Membership" button.
6. Token holders see the content immediately.

= Features =

* One shortcode for any content type — posts, pages, custom post types
* Multiple gated sections per page with different `project_id` values
* `[token_gate]` alias — spec-compatible shortcode (same as `[token_membership]`)
* `[token_buy]` standalone buy button — add a purchase/renew button anywhere without gating content
* Gutenberg block support — **Token Gate** block with InnerBlocks and sidebar controls
* MetaMask + compatible wallet support (EIP-1193 / EIP-6963 multi-wallet)
* Native POL payments and ERC-20 payments (USDC, USDT, any whitelisted token)
* Shared Polygon contract — no deployment needed per project
* API key stays server-side (PHP proxy) — never exposed to the browser
* Redis-cached access checks (60-second TTL) — fast, minimal blockchain calls
* Token expiration support — memberships with `membershipDays` show an "Expired" state with a renew button
* Fully independent from the NFT SaaS artwork plugin

= Creator Dashboard =

Manage your membership projects at [ spntn.com/token_membership ]( https://spntn.com/token_membership ).

* **Email verification** — accounts require email confirmation before first login
* **Project settings** — configure name, price (wei), supply cap, membership duration (`membershipDays`), and webhook URL
* **Revenue tracking** — see cumulative revenue per project aggregated from all on-chain mints
* **Member list + revoke** — view all token holders with mint dates; revoke a member's access with one click (invalidates Redis cache immediately)
* **Webhook notifications** — set a Webhook URL on any project to receive HTTP POST events for `member.created` and `member.revoked`
* **Free to use** — no monthly fee, no project or member limits. The platform takes **3%** of each on-chain mint; creators keep 97%
* **Quick Setup Code** — generate an 8-character one-time code in the dashboard; paste it into the plugin settings to auto-fill all fields instantly

= Shortcode Attributes =

`[token_membership project_id="..." title="..." description="..."]`

* `project_id` (required unless set as default in Settings) — your project ID from the SPNTN dashboard
* `title` — override the "Members Only" heading
* `description` — override the gate description text

`[token_gate project="..."]` — alias for `[token_membership]` with `project` instead of `project_id`.

`[token_buy project_id="..." label="..." description="..."]` — renders a buy button (and member badge when access is granted) without wrapping any content.

= Payment Flow =

1. Buyer clicks "Get Membership"
2. Plugin calls WordPress AJAX → PHP forwards to backend `/api/v2/access/sign` (API key stays server-side)
3. Backend returns `{ signature, contractAddress, artist, tokenURI, priceWei }`
4. Browser calls `buyAndMint(artist, tokenURI, price, signature)` with `msg.value = priceWei` on Polygon
5. Contract verifies signature → mints NFT to buyer → sends ~97% to creator wallet instantly
6. Plugin records mint in backend DB and re-checks access
7. Content unlocks

For ERC-20 payments (USDC etc.) the flow uses `buyAndMintWithToken` with a prior `approve` call.

= Requirements =

* A SPNTN Token Membership backend instance (Railway or self-hosted)
* A SPNTN API key (generated in the dashboard)
* At least one project created with a Creator Wallet set and activated

== Installation ==

1. Upload the `token-membership` folder to `/wp-content/plugins/`.
2. Activate the plugin in **Plugins → Installed Plugins**.
3. In the SPNTN dashboard, open a project and click **⚡ Generate Setup Code**.
4. Go to **Settings → Token Membership**, paste the 8-character code in the **Quick Setup** field, and click **Apply**.
5. API URL, API Key, and Default Project ID are filled automatically.

Alternatively, fill the three fields manually from the SPNTN dashboard.

== Frequently Asked Questions ==

= Do users need a crypto wallet? =
Yes. Users connect MetaMask (or any EIP-1193 compatible wallet). The plugin automatically prompts them to switch to Polygon Mainnet.

= Is the API key exposed to the browser? =
No. All signing requests go through a WordPress PHP proxy (wp-ajax). The API key is stored in the WordPress options table and never sent to the frontend.

= Can I have multiple gated sections on the same page? =
Yes — use a different `project_id` attribute per shortcode block.

= Do I need to deploy a smart contract? =
No. All projects share a single contract already deployed on Polygon (`0xF912D97BB2fF635c3D432178e46A16930B5Af51A`). Just activate your project in the dashboard.

= Is this the same as the NFT SaaS plugin? =
No. The NFT SaaS plugin is for artists to sell individual NFT artworks from their site. This plugin is for creators to gate member-only content. They can coexist on the same WordPress installation.

= Where do the sale proceeds go? =
Directly to the **Creator Wallet** you set in the dashboard — in the same on-chain transaction. No withdrawal needed.

== Changelog ==

= 1.4.0 =
* Platform: switched to **free + 3% per mint** model — no subscription, no project or member limits
* Dashboard: Quick Setup Code — generate an 8-char one-time code to auto-configure the WordPress plugin (30-min TTL)
* Plugin: Quick Setup box in Settings — paste code, click Apply, all fields fill automatically
* Plugin: Test Connection button — verify API key from the settings page
* Frontend: helpful wallet install prompts for desktop & mobile (MetaMask, Trust Wallet)
* WordPress gate: no-wallet state shows deep links for MetaMask Mobile & Trust Wallet on mobile
* Dashboard: member table now shows token expiry date with visual expiry indicator
* Token metadata: "Membership Duration" attribute added to IPFS token URI

= 1.3.1 =
* Platform: email verification required for new dashboard accounts
* Platform: revenue tracking per project (cumulative `priceWei` aggregation)
* Platform: member revoke endpoint — removes token record and invalidates Redis cache
* Platform: webhook URL field in project settings — fires `member.created` and `member.revoked` events
* Platform: `membershipDays` now configurable from the dashboard project creation and settings forms
* Fix: legacy dashboard accounts (created before verification requirement) can log in without re-verifying

= 1.3.0 =
* Added `[token_buy]` standalone buy / membership button shortcode
* Added `[token_gate]` shortcode alias (spec-compatible `project` attribute)
* Added Gutenberg **Token Gate** block with InnerBlocks and sidebar inspector controls
* Added token expiration support — expired memberships show an amber "Expired" state with a Renew button
* Added member badge in access state for `[token_buy]` ("Active Member" with expiry date when applicable)
* Bumped Redis access cache TTL from 30s to 60s
* Backend: `membershipDays` field on projects; `expiryDate` stored on access tokens
* Backend: `/check` now returns `expired: true` and `expiresAt` fields

= 1.2.0 =
* Added EIP-6963 multi-wallet support (Coinbase, Rainbow, Trust, etc.)
* Removed browser `alert()` — wallet errors now shown inline in the gate widget
* Added celebration / success state shown for 2.5s after a successful mint
* Added connect-error display inside the gate (no more silent failures)

= 1.1.0 =
* Switched to shared singleton contract — no per-project deployment required
* Added ERC-20 payment path (USDC, USDT)
* API key is now kept server-side via PHP AJAX proxy
* Improved tokenId extraction using Transfer event topic filter
* Fixed wp_ajax_nopriv hook name for record_mint

= 1.0.0 =
* Initial release.

== External services ==

The Token Membership plugin interacts with several external services:

- **NFT SaaS Backend API**
  - URL: https://nft-saas-production.up.railway.app
  - Purpose: Handles authentication, project/project access, mint signatures, webhook events, and IPFS uploads. All plugin features rely on this API for core operations.

- **Polygon Mainnet**
  - Purpose: Membership token minting and verification are performed on Polygon Mainnet via smart contracts. The plugin interacts with Polygon using ethers.js.

- **IPFS**
  - Purpose: NFT metadata and media files are uploaded to IPFS for decentralized storage.

- **Webhook Integrations**
  - Purpose: Creators can configure webhook URLs to receive real-time HTTP POST events from the backend when membership status changes.

- **Explorer Links**
  - Purpose: Plugin provides links to Polygon block explorers (e.g., Polygonscan) for transaction and token verification.

API keys and sensitive credentials are stored server-side and never exposed to frontend users.
