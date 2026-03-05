/**
 * access-check.js
 * Token Membership for WordPress — gate logic.
 *
 * States per gate:
 *   loading      → initial
 *   disconnected → wallet not connected
 *   no-access    → wallet connected, no token (shows price + buy button)
 *   minting      → purchase in progress (4-step indicator)
 *   success      → mint just completed — brief celebration before content shown
 *   access       → content unlocked
 *   error        → recoverable error (shows message + retry button)
 */

(function () {
  'use strict';

  const config = window.TM_Config || {};

  const TM_AccessCheck = {

    checkAll() {
      document.querySelectorAll('.tm-gate').forEach((gate) => this.checkGate(gate));
    },

    // retries: how many more times to retry if access comes back false
    // (handles Redis 30s TTL after a fresh mint)
    // celebrate: show a brief success state before showing content (after mint)
    async checkGate(gate, { retries = 0, celebrate = false } = {}) {
      const projectId = gate.dataset.projectId || config.defaultProjectId;
      if (!projectId) { this._setState(gate, 'disconnected'); return; }

      const wallet = window.TM_Wallet.getAddress();
      if (!wallet) { this._setState(gate, 'disconnected'); return; }

      this._setState(gate, 'loading');

      try {
        const response = await fetch(
          config.apiUrl.replace(/\/$/, '') + '/api/v2/access/check',
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ projectId, walletAddress: wallet }),
          }
        );

        if (!response.ok) throw new Error('Server error (' + response.status + ')');
        const data = await response.json();

        if (data.access) {
          if (celebrate) {
            // Show success celebration for 2.5s, then reveal content
            this._setState(gate, 'success');
            await _sleep(2500);
          }
          this._setState(gate, 'access');
        } else {
          if (retries > 0) {
            await _sleep(1800);
            return this.checkGate(gate, { retries: retries - 1, celebrate });
          }
          // Save price for display in no-access card
          if (data.price) {
            gate.dataset.priceWei  = data.price;
            gate.dataset.currency  = data.currency || 'POL';
          }
          this._populatePrice(gate);
          this._setState(gate, 'no-access');
        }
      } catch (err) {
        console.error('[Token Membership] access/check failed:', err);
        if (retries > 0) {
          await _sleep(1800);
          return this.checkGate(gate, { retries: retries - 1, celebrate });
        }
        this._setState(gate, 'disconnected');
      }
    },

    // ── Helpers ─────────────────────────────────────────────────────────────

    _populatePrice(gate) {
      const el  = gate.querySelector('.tm-price-display');
      if (!el) return;
      const wei = gate.dataset.priceWei;
      if (!wei) { el.style.display = 'none'; return; }
      try {
        const amount    = Number(BigInt(wei)) / 1e18;
        const formatted = (amount % 1 === 0) ? amount.toString() : amount.toFixed(4);
        const currency  = gate.dataset.currency || 'POL';
        el.textContent  = 'Price: ' + formatted + ' ' + currency;
        el.style.display = '';
      } catch { el.style.display = 'none'; }
    },

    _setState(gate, state) {
      gate.querySelectorAll('.tm-state').forEach((el) => {
        el.style.display = el.classList.contains('tm-state--' + state) ? '' : 'none';
      });
    },

    _setStep(gate, step) {
      gate.querySelectorAll('.tm-progress-step').forEach((el) => {
        const n = parseInt(el.dataset.step, 10);
        el.classList.toggle('tm-progress-step--done',    n < step);
        el.classList.toggle('tm-progress-step--active',  n === step);
        el.classList.toggle('tm-progress-step--pending', n > step);
      });
      const statusEl = gate.querySelector('.tm-minting-status');
      if (statusEl) {
        const labels = [
          null,
          'Preparing your transaction…',
          'Please confirm in your wallet…',
          'Confirming on Polygon…',
          'Unlocking your content…',
        ];
        statusEl.textContent = labels[step] || '';
      }
    },

    _showError(gate, message) {
      const el = gate.querySelector('.tm-error-msg');
      if (el) el.textContent = message;
      this._setState(gate, 'error');
    },
  };

  // ── Event: Connect wallet ──────────────────────────────────────────────────
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.tm-btn--connect');
    if (!btn) return;
    btn.disabled = true;
    btn.textContent = 'Connecting…';
    const gate = btn.closest('.tm-gate');
    try {
      await window.TM_Wallet.connect();
      TM_AccessCheck.checkAll();
    } catch (err) {
      console.error('[Token Membership] connect failed:', err);
      btn.disabled = false;
      btn.textContent = 'Connect Wallet';
      if (gate) TM_AccessCheck._showError(gate, err.message);
    }
  });

  // ── Event: Retry ───────────────────────────────────────────────────────────
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.tm-btn--retry');
    if (!btn) return;
    const gate = btn.closest('.tm-gate');
    if (gate) TM_AccessCheck.checkGate(gate);
  });

  // ── Event: Get Membership ──────────────────────────────────────────────────
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.tm-btn--buy');
    if (!btn) return;

    const gate      = btn.closest('.tm-gate');
    const projectId = btn.dataset.projectId || config.defaultProjectId;
    const wallet    = window.TM_Wallet.getAddress();
    if (!wallet || !projectId) return;

    TM_AccessCheck._setState(gate, 'minting');
    TM_AccessCheck._setStep(gate, 1);

    try {
      // ── Step 1: Server-side signature ──────────────────────────────────────
      const sigForm = new FormData();
      sigForm.append('action',       'tm_sign_mint');
      sigForm.append('nonce',        config.nonce);
      sigForm.append('projectId',    projectId);
      sigForm.append('buyerAddress', wallet);

      const sigRes = await fetch(config.ajaxUrl, { method: 'POST', body: sigForm });
      if (!sigRes.ok) throw new Error('Could not reach the server. Please try again.');
      const sigJson = await sigRes.json();
      if (!sigJson.success) {
        throw new Error(sigJson.data?.message || sigJson.data?.error || 'Could not prepare your transaction.');
      }
      const sigData = sigJson.data;

      // ── Step 2: Wallet interaction ─────────────────────────────────────────
      TM_AccessCheck._setStep(gate, 2);

      const provider = new ethers.BrowserProvider(window.ethereum);
      const signer   = await provider.getSigner();
      let tx;

      if (sigData.paymentToken) {
        // ERC-20: approve → buyAndMintWithToken
        const erc20 = new ethers.Contract(
          sigData.paymentToken,
          ['function approve(address spender, uint256 amount) external returns (bool)'],
          signer
        );
        const approveTx = await erc20.approve(sigData.contractAddress, BigInt(sigData.priceWei));
        await approveTx.wait();

        const contract = new ethers.Contract(
          sigData.contractAddress,
          ['function buyAndMintWithToken(address artist, string calldata tokenURI, uint256 price, address token, bytes calldata signature) external'],
          signer
        );
        tx = await contract.buyAndMintWithToken(
          sigData.artist, sigData.tokenURI,
          BigInt(sigData.priceWei), sigData.paymentToken, sigData.signature
        );
      } else {
        // Native POL: buyAndMint
        const contract = new ethers.Contract(
          sigData.contractAddress,
          ['function buyAndMint(address artist, string calldata tokenURI, uint256 price, bytes calldata signature) external payable'],
          signer
        );
        tx = await contract.buyAndMint(
          sigData.artist, sigData.tokenURI,
          BigInt(sigData.priceWei), sigData.signature,
          { value: BigInt(sigData.priceWei) }
        );
      }

      // ── Step 3: On-chain confirmation ──────────────────────────────────────
      TM_AccessCheck._setStep(gate, 3);
      const receipt = await tx.wait();

      // Extract tokenId from ERC-721 Transfer event
      const TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
      const transferLog    = receipt.logs.find(l => l.topics[0] === TRANSFER_TOPIC);
      const tokenId        = transferLog ? parseInt(transferLog.topics[3], 16) : 0;

      // Record mint in backend DB
      const recForm = new FormData();
      recForm.append('action',        'tm_record_mint');
      recForm.append('nonce',         config.nonce);
      recForm.append('projectId',     projectId);
      recForm.append('walletAddress', wallet);
      recForm.append('tokenId',       tokenId);
      recForm.append('txHash',        receipt.hash);
      await fetch(config.ajaxUrl, { method: 'POST', body: recForm });

      // ── Step 4: Unlock — retry up to 3× to bypass Redis TTL ───────────────
      TM_AccessCheck._setStep(gate, 4);
      await TM_AccessCheck.checkGate(gate, { retries: 3, celebrate: true });

    } catch (err) {
      console.error('[Token Membership] mint failed:', err);

      // User rejected the transaction — go back silently
      if (err?.code === 4001 || err?.code === 'ACTION_REJECTED' ||
          err?.info?.error?.code === 4001) {
        TM_AccessCheck._setState(gate, 'no-access');
        return;
      }

      TM_AccessCheck._showError(
        gate,
        err.reason || err.shortMessage || err.message || 'Something went wrong. Please try again.'
      );
    }
  });

  // ── Init ───────────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    // Brief delay to let wallet-connect.js restore previous session
    setTimeout(() => TM_AccessCheck.checkAll(), 100);
  });

  if (window.TM_Wallet) {
    window.TM_Wallet.onAccountChange(() => TM_AccessCheck.checkAll());
  }

  window.TM_AccessCheck = TM_AccessCheck;

  // ── Util ───────────────────────────────────────────────────────────────────
  function _sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

})();
