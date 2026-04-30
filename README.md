# Token Membership

![Version](https://img.shields.io/badge/version-1.4.0-blue)
![License](https://img.shields.io/badge/license-GPL--2.0-green)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4)
![Chain](https://img.shields.io/badge/chain-Polygon-8247e5)

Gate your WordPress content behind a blockchain membership token. Users who hold the NFT see the content — everyone else sees a "Get Membership" button.

---

## How it works

```
Visitor arrives at gated content
        ↓
Sees "Members Only" gate with Connect Wallet button
        ↓
Connects MetaMask — plugin checks token ownership on-chain
        ↓
Token holder → content unlocks instantly
        ↓
No token → "Get Membership" button appears
        ↓
Buyer mints NFT via MetaMask (POL or USDC)
        ↓
97% to creator wallet, 3% platform fee — on-chain, automatic
        ↓
Content unlocks immediately after minting
```

---

## Quick start

### 1. Install

Upload the plugin folder to `/wp-content/plugins/` and activate it in **Plugins → Installed Plugins**.

### 2. Get your credentials

Two options:

**Option A — Quick Setup (recommended):**
1. Log in to the SPNTN dashboard at [spntn.com/token_membership](https://spntn.com/token_membership)
2. Open your project and click **Generate Setup Code**
3. Go to **Settings → Token Membership** in WordPress
4. Paste the 8-character code into **Quick Setup** and click **Apply** — API URL, API Key, and Project ID fill automatically

**Option B — Manual:**
Copy the API URL, API Key, and Project ID from the dashboard and paste them into **Settings → Token Membership**.

### 3. Gate your content

Wrap any content in the shortcode:

```
[token_membership project_id="YOUR_PROJECT_ID"]
  Your protected content goes here.
[/token_membership]
```

Replace `YOUR_PROJECT_ID` with the Project ID from the SPNTN dashboard.

### 4. Add a buy button (optional)

Place a standalone purchase button anywhere without wrapping content:

```
[token_buy project_id="YOUR_PROJECT_ID"]
```

---

## Shortcodes

| Shortcode | Purpose |
|---|---|
| `[token_membership project_id="..."]` | Gate content — shows content to token holders, buy button to others |
| `[token_gate project="..."]` | Alias for `[token_membership]` — uses `project` instead of `project_id` |
| `[token_buy project_id="..."]` | Standalone buy/renew button — shows member badge when access is granted |

### Shortcode attributes

`[token_membership project_id="..." title="..." description="..."]`

| Attribute | Required | Description |
|---|---|---|
| `project_id` | Yes (or set as default in Settings) | Project ID from the SPNTN dashboard |
| `title` | No | Override the "Members Only" heading |
| `description` | No | Override the gate description text |

---

## Purchase flow

1. Visitor clicks **Get Membership** — MetaMask opens.
2. Plugin calls backend via PHP (API key stays server-side).
3. Backend returns a signature authorizing the mint.
4. MetaMask confirms — NFT minted to buyer's wallet on Polygon.
5. 97% goes to the creator wallet instantly, 3% to the platform.
6. Content unlocks — no page reload needed.

For ERC-20 payments (USDC, USDT): MetaMask first asks for token spend approval, then the mint transaction.

---

## Creator dashboard

Manage everything at [spntn.com/token_membership](https://spntn.com/token_membership).

| Feature | Detail |
|---|---|
| Projects | Create projects with name, price, supply cap, membership duration |
| Creator Wallet | Receives 97% of every sale on-chain |
| Revenue tracking | Cumulative revenue per project |
| Member list | All token holders with mint dates |
| Revoke access | Remove a member instantly (invalidates cache) |
| Webhooks | HTTP POST events for `member.created` and `member.revoked` |
| Quick Setup Code | 8-character one-time code to auto-configure the plugin (30-min TTL) |

---

## Supported tokens and chains

| Payment | Details |
|---|---|
| POL (native) | Polygon Mainnet |
| ERC-20 | USDC, USDT, or any whitelisted token |

All projects share a single deployed contract on Polygon — no per-project deployment needed.

| | |
|---|---|
| Contract | `0xF912D97BB2fF635c3D432178e46A16930B5Af51A` |
| Standard | ERC-721 |
| Protocol fee | 3% per mint |
| Creator receives | 97% per mint |

---

## Gutenberg block

The **Token Gate** block is available in the block editor. Select it from the inserter, add your `project_id` in the sidebar, and place content inside the block. Works identically to the shortcode.

---

## Pricing

| | |
|---|---|
| Plugin | Free |
| API key | Free |
| Protocol fee | 3% per mint (on-chain, automatic) |
| Creator receives | 97% of each membership price |

No subscriptions. No monthly fees. No project or member limits.

---

## Requirements

* WordPress 6.0 or higher
* PHP 8.0 or higher
* HTTPS on the site (required for MetaMask)
* Buyers need MetaMask or a compatible EVM wallet

---

## Security

- API key is stored server-side in WordPress options — never sent to the browser
- All signing requests go through a PHP AJAX proxy (`wp-ajax`)
- All AJAX endpoints are protected with `check_ajax_referer`
- Access checks are Redis-cached with a 60-second TTL for speed

---

## External services

The plugin connects to:

* **SPNTN Token Membership backend** (`https://nft-saas-production.up.railway.app`) — authentication, project management, mint signatures, access verification, IPFS uploads.
* **Polygon Mainnet** — token minting and ownership verification via MetaMask.
* **IPFS** — NFT metadata and media stored permanently via Pinata.

---

## Bundled libraries

| Library | Version | License |
|---|---|---|
| ethers.js | 6.13.2 | MIT |

---

## Support

Email: info@spntn.com
WordPress.org: https://wordpress.org/plugins/token-membership

---

## License

GPL v2 or later — https://www.gnu.org/licenses/gpl-2.0.html
