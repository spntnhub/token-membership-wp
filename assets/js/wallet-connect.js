/**
 * wallet-connect.js
 * Token Membership for WordPress — wallet connection with EIP-6963 support.
 *
 * Exposes:  window.TM_Wallet
 *   .connect()                 → Promise<string>  wallet address
 *   .disconnect()
 *   .getAddress()              → string | null
 *   .onAccountChange(cb)
 *   .availableProviders        → array of EIP-6963 provider descriptors
 */

(function () {
  'use strict';

  const STORAGE_KEY    = 'tm_wallet_address';
  const POLYGON_CHAIN_ID = '0x89'; // 137 decimal

  // ── EIP-6963 provider discovery ──────────────────────────────────────────
  // Collect every wallet that announces itself via the EIP-6963 standard.
  // Falls back to window.ethereum (MetaMask legacy injection).
  const _eip6963Providers = [];

  window.addEventListener('eip6963:announceProvider', (event) => {
    if (event.detail && event.detail.provider) {
      // Avoid duplicates by UUID
      const uuid = event.detail.info?.uuid;
      if (!uuid || !_eip6963Providers.some(p => p.info?.uuid === uuid)) {
        _eip6963Providers.push(event.detail);
      }
    }
  });

  // Request all wallets to announce themselves
  window.dispatchEvent(new Event('eip6963:requestProvider'));

  // Pick the best available provider:
  // 1. First EIP-6963 announced provider
  // 2. window.ethereum legacy fallback
  function _getProvider() {
    if (_eip6963Providers.length > 0) return _eip6963Providers[0].provider;
    if (window.ethereum) return window.ethereum;
    return null;
  }

  const TM_Wallet = {
    _address:  localStorage.getItem(STORAGE_KEY) || null,
    _listeners: [],

    get availableProviders() { return _eip6963Providers; },

    getAddress() {
      return this._address;
    },

    async connect() {
      const provider = _getProvider();
      if (!provider) {
        // No alert — throw so access-check.js can show the error in the gate UI
        throw new Error('No Web3 wallet found. Please install MetaMask or another browser wallet.');
      }

      // Request accounts
      const accounts = await provider.request({ method: 'eth_requestAccounts' });
      if (!accounts || accounts.length === 0) throw new Error('No accounts returned.');

      // Switch to Polygon if needed
      try {
        await provider.request({
          method: 'wallet_switchEthereumChain',
          params: [{ chainId: POLYGON_CHAIN_ID }],
        });
      } catch (switchError) {
        if (switchError.code === 4902) {
          await provider.request({
            method: 'wallet_addEthereumChain',
            params: [{
              chainId: POLYGON_CHAIN_ID,
              chainName: 'Polygon Mainnet',
              nativeCurrency: { name: 'POL', symbol: 'POL', decimals: 18 },
              rpcUrls: ['https://polygon-rpc.com'],
              blockExplorerUrls: ['https://polygonscan.com'],
            }],
          });
        } else {
          throw switchError;
        }
      }

      this._setAddress(accounts[0]);

      // Re-attach account/chain listeners to the chosen provider
      provider.on('accountsChanged', (accs) => {
        TM_Wallet._setAddress(accs[0] || null);
        if (window.TM_AccessCheck) window.TM_AccessCheck.checkAll();
      });
      provider.on('chainChanged', () => {
        window.location.reload();
      });

      return this._address;
    },

    disconnect() {
      this._setAddress(null);
    },

    onAccountChange(cb) {
      this._listeners.push(cb);
    },

    _setAddress(addr) {
      this._address = addr ? addr.toLowerCase() : null;
      if (this._address) {
        localStorage.setItem(STORAGE_KEY, this._address);
      } else {
        localStorage.removeItem(STORAGE_KEY);
      }
      this._listeners.forEach((cb) => cb(this._address));
    },
  };

  // Legacy window.ethereum listeners (for wallets that don't support EIP-6963)
  if (window.ethereum) {
    window.ethereum.on('accountsChanged', (accounts) => {
      // Only fire if we're using the legacy provider (no EIP-6963 provider took over)
      if (_eip6963Providers.length === 0) {
        TM_Wallet._setAddress(accounts[0] || null);
        if (window.TM_AccessCheck) window.TM_AccessCheck.checkAll();
      }
    });
    window.ethereum.on('chainChanged', () => {
      if (_eip6963Providers.length === 0) window.location.reload();
    });
  }

  window.TM_Wallet = TM_Wallet;
})();

