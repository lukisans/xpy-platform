<?php
defined('ABSPATH') || exit;

// Handle form submissions
if (isset($_POST['lafapay_action'])) {
    if ($_POST['lafapay_action'] === 'save_wallet' && !empty($_POST['wallet_address'])) {
        update_option('lafapay_wallet_address', sanitize_text_field($_POST['wallet_address']));
        echo '<div class="notice notice-success"><p>Wallet address saved!</p></div>';
    }

    if ($_POST['lafapay_action'] === 'save_sdk_key' && !empty($_POST['sdk_key'])) {
        update_option('lafapay_sdk_key', sanitize_text_field($_POST['sdk_key']));
        echo '<div class="notice notice-success"><p>SDK Key saved!</p></div>';
    }
}

$wallet_address = get_option('lafapay_wallet_address', '');
$sdk_key = get_option('lafapay_sdk_key', '');
$step1_done = !empty($wallet_address);
$step2_done = !empty($sdk_key);
?>

<div class="wrap">
    <h1>Lafapay Setup</h1>

    <div style="max-width: 800px;">

        <!-- Step 1: Create EVM Wallet -->
        <div class="card" style="padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">
            <h2>Step 1: Connect EVM Wallet <?php echo $step1_done ? '✅' : ''; ?></h2>
            <p>Connect your EVM-compatible wallet to receive crypto payments.</p>

            <?php if (!$step1_done): ?>
                <div style="margin-bottom: 20px;">
                    <button id="connectMetaMask" class="button button-primary" style="margin-right: 10px;">
                        Connect MetaMask Wallet
                    </button>
                    <span id="connectionStatus" style="margin-left: 10px; color: #666;"></span>
                </div>
            <?php endif; ?>

            <form method="post" id="walletForm">
                <table class="form-table">
                    <tr>
                        <th scope="row">Wallet Address</th>
                        <td>
                            <input type="text" name="wallet_address" id="walletAddress"
                                   value="<?php echo esc_attr($wallet_address); ?>"
                                   placeholder="0x..." style="width: 400px;" />
                            <p class="description">Your EVM wallet address (starts with 0x)</p>
                        </td>
                    </tr>
                </table>
                <input type="hidden" name="lafapay_action" value="save_wallet" />
                <p><input type="submit" class="button button-primary" value="Save Wallet Address" /></p>
            </form>
        </div>

        <!-- Step 2: Register to LiFi Platform -->
        <div class="card" style="padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; <?php echo !$step1_done ? 'opacity: 0.5;' : ''; ?>">
            <h2>Step 2: Register to LiFi Platform <?php echo $step2_done ? '✅' : ''; ?></h2>
            <p>Register with LiFi to get your SDK API key for processing crypto payments.</p>

            <?php if ($step1_done && !$step2_done): ?>
                <p>
                    <a href="https://li.fi/" target="_blank" class="button button-primary">
                        Register with LiFi
                    </a>
                </p>
                <p><em>After registration, get your API key and enter it below:</em></p>
            <?php elseif (!$step1_done): ?>
                <p><em>Complete Step 1 first to proceed.</em></p>
            <?php endif; ?>

            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">LiFi SDK Key</th>
                        <td>
                            <input type="text" name="sdk_key" value="<?php echo esc_attr($sdk_key); ?>"
                                   placeholder="Your LiFi API Key" style="width: 400px;"
                                   <?php echo !$step1_done ? 'disabled' : ''; ?> />
                            <p class="description">API key from your LiFi dashboard</p>
                        </td>
                    </tr>
                </table>
                <input type="hidden" name="lafapay_action" value="save_sdk_key" />
                <p><input type="submit" class="button button-primary" value="Save SDK Key"
                         <?php echo !$step1_done ? 'disabled' : ''; ?> /></p>
            </form>
        </div>

        <!-- Step 3: Enable Payment Gateway -->
        <div class="card" style="padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; <?php echo !$step2_done ? 'opacity: 0.5;' : ''; ?>">
            <h2>Step 3: Enable Payment Gateway <?php echo ($step1_done && $step2_done) ? '✅' : ''; ?></h2>
            <p>Once both steps above are complete, you can enable Lafapay as a payment option.</p>

            <?php if ($step1_done && $step2_done): ?>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=lafapay'); ?>"
                       class="button button-primary">
                        Configure Payment Gateway
                    </a>
                </p>
                <p><strong>Setup Complete!</strong> You can now enable Lafapay payments in WooCommerce settings.</p>
            <?php else: ?>
                <p><em>Complete steps 1 and 2 first.</em></p>
            <?php endif; ?>
        </div>

        <!-- Current Status -->
        <div class="card" style="padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; background: #f9f9f9;">
            <h3>Current Setup Status</h3>
            <ul>
                <li>Wallet Address: <?php echo $step1_done ? '✅ Configured' : '❌ Not set'; ?></li>
                <li>LiFi SDK Key: <?php echo $step2_done ? '✅ Configured' : '❌ Not set'; ?></li>
                <li>Gateway Status: <?php echo ($step1_done && $step2_done) ? '✅ Ready to enable' : '❌ Setup incomplete'; ?></li>
            </ul>
        </div>

    </div>
</div>

<!-- MetaMask SDK Integration -->
<script src="https://c0f4f41c-2f55-4863-921b-sdk-docs.github.io/cdn/metamask-sdk.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let sdk;
    let provider;

    // Initialize MetaMask SDK
    try {
        sdk = new MetaMaskSDK.MetaMaskSDK({
            logging: {
                developerMode: false,
            },
            dappMetadata: {
                name: 'Lafapay - WordPress Plugin',
                url: window.location.origin,
            },
        });
    } catch (error) {
        console.error('Failed to initialize MetaMask SDK:', error);
    }

    const connectButton = document.getElementById('connectMetaMask');
    const statusElement = document.getElementById('connectionStatus');
    const walletAddressInput = document.getElementById('walletAddress');

    if (connectButton) {
        connectButton.addEventListener('click', async function() {
            if (!sdk) {
                updateStatus('MetaMask SDK not available', 'error');
                return;
            }

            connectButton.disabled = true;
            connectButton.textContent = 'Connecting...';
            updateStatus('Connecting to MetaMask...', 'info');

            try {
                // Connect to MetaMask
                const accounts = await sdk.connect();
                provider = sdk.getProvider();

                if (accounts && accounts.length > 0) {
                    const walletAddress = accounts[0];
                    walletAddressInput.value = walletAddress;
                    updateStatus('✅ Connected successfully!', 'success');
                    connectButton.textContent = 'Connected';
                    connectButton.style.backgroundColor = '#46b450';

                    // Optional: Auto-save the wallet address
                    // You can uncomment the next line if you want to auto-submit
                    // document.getElementById('walletForm').submit();
                } else {
                    throw new Error('No accounts returned');
                }
            } catch (error) {
                console.error('Connection error:', error);
                updateStatus('❌ Connection failed: ' + error.message, 'error');
                connectButton.disabled = false;
                connectButton.textContent = 'Connect MetaMask Wallet';
            }
        });
    }

    function updateStatus(message, type) {
        if (statusElement) {
            statusElement.textContent = message;
            statusElement.style.color = getStatusColor(type);
        }
    }

    function getStatusColor(type) {
        switch (type) {
            case 'success': return '#46b450';
            case 'error': return '#dc3232';
            case 'info': return '#0073aa';
            default: return '#666';
        }
    }

    // Check if MetaMask is already connected
    if (sdk && typeof window.ethereum !== 'undefined') {
        window.ethereum.request({ method: 'eth_accounts' })
            .then(accounts => {
                if (accounts.length > 0 && !walletAddressInput.value) {
                    updateStatus('MetaMask detected', 'info');
                }
            })
            .catch(console.error);
    }
});
</script>

<style>
#connectMetaMask:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.lafapay-status {
    padding: 8px 12px;
    border-radius: 4px;
    font-weight: 500;
    display: inline-block;
    margin-left: 10px;
}

.lafapay-status.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.lafapay-status.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.lafapay-status.info {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}
</style>
