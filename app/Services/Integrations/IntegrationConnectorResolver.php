<?php

namespace App\Services\Integrations;

use App\Services\Integrations\AiServices\OpenAiConnector;
use App\Services\Integrations\AiServices\GeminiConnector;
use App\Services\Integrations\AiServices\AnthropicConnector;
use App\Services\Integrations\AiServices\ElevenLabsConnector;
use App\Services\Integrations\Communication\TelegramBotConnector;
use App\Services\Integrations\Communication\LineConnector;
use App\Services\Integrations\Communication\WeChatConnector;
use App\Services\Integrations\Communication\TwilioSmsConnector;
use App\Services\Integrations\Communication\VonageSmsConnector;
// Deepgram/Groq/HuggingFace connector classes still exist on disk
// (app/Services/Integrations/AiServices/) but are deliberately not
// listed below as of 5 Aug 2026 — per Chris: dropped from the visible
// AI Services list since nothing in GeneralLink uses them yet and
// they're not providers a typical agent already has an account with.
// Re-adding one later is just uncommenting its import + array line below.
// use App\Services\Integrations\AiServices\DeepgramConnector;
// use App\Services\Integrations\AiServices\GroqConnector;
// use App\Services\Integrations\AiServices\HuggingFaceConnector;

// NEW 4 Aug 2026 — Integration Hub Phase 1. This is the single place
// that lists every category and provider from Chris's spec. Phase 1
// only wires up real connector classes for the "ai_services" category
// (5 providers) — every other category is listed with its providers so
// the Hub shows the full planned shape, but each provider's `connector`
// is null until a later phase builds it (the UI shows "Coming soon").
// Adding a provider later = one array entry + one connector class, no
// schema change and no change to the controller or views.
class IntegrationConnectorResolver
{
    public static function categories(): array
    {
        return [
            'ai_services' => [
                'label' => 'AI Services',
                'icon' => 'ti-brain',
                'description' => 'Chat, text, and voice AI providers.',
                'providers' => [
                    // NEW 5 Aug 2026 — 'help' explains WHY connecting this
                    // helps the agent and WHERE to get the key, shown right
                    // on this screen (not just told once in chat) so any
                    // agent lands here already knowing what to do. Same
                    // wording is given to Carolyn in AiAssistantService.
                    'openai' => ['label' => 'OpenAI', 'credential_type' => 'API_KEY', 'connector' => OpenAiConnector::class, 'help' => 'Lets you use Document Reading (sales document upload) with your own OpenAI account instead of company Document Credit. Get a key at platform.openai.com/api-keys — this is separate from a ChatGPT Plus subscription and billed on its own.'],
                    'gemini' => ['label' => 'Google Gemini', 'credential_type' => 'API_KEY', 'connector' => GeminiConnector::class, 'help' => 'Lets you use Document Reading (sales document upload) with your own Gemini account instead of company Document Credit. Get a free key at aistudio.google.com — this is separate from a Gemini Pro/Advanced subscription and billed on its own (it has a free tier).'],
                    // 'deepgram' => ['label' => 'Deepgram', ...],   -- dropped 5 Aug 2026, see note above
                    // 'groq' => ['label' => 'Groq', ...],           -- dropped 5 Aug 2026, see note above
                    // 'huggingface' => ['label' => 'Hugging Face', ...], -- dropped 5 Aug 2026, see note above
                    // CHANGED 6 Aug 2026 — per Chris: these were stubbed
                    // with connector => null and a note deferring to the
                    // Profile page, which meant an agent had to paste the
                    // SAME key twice (once here to "connect" it in name
                    // only, once again on the Profile page for it to
                    // actually work). Now these are real, testable Hub
                    // connectors — connect the key HERE once, then Profile
                    // just lets the agent pick it. See VoicePreferenceService
                    // and TextChatPreferenceService.
                    'anthropic' => ['label' => 'Anthropic (Claude)', 'credential_type' => 'API_KEY', 'connector' => AnthropicConnector::class, 'help' => 'Lets Carolyn\'s text chat use your own Anthropic account instead of the shared default key. Get a key at console.anthropic.com — this is separate from a Claude.ai subscription and billed on its own. Once connected here, turn it on in My Profile > Text Chat.'],
                    'elevenlabs' => ['label' => 'ElevenLabs', 'credential_type' => 'API_KEY', 'connector' => ElevenLabsConnector::class, 'help' => 'Lets Carolyn speak using your own ElevenLabs voice and credits instead of the free built-in voice. Get a key at elevenlabs.io/app/settings/api-keys. Once connected here, pick it in My Profile > Voice Assistant.'],
                ],
            ],
            'communication' => [
                'label' => 'Communication', 'icon' => 'ti-message-circle', 'description' => 'Messaging and SMS platforms.',
                'providers' => [
                    // WIRED UP 6 Aug 2026 — per Chris, needed for a real demo
                    // to his boss: this now genuinely tests against Meta's
                    // WhatsApp Cloud API (see WhatsAppCloudConnector) instead
                    // of just storing an unused credential.
                    'whatsapp' => ['label' => 'WhatsApp Business API', 'credential_type' => 'WHATSAPP_CLOUD', 'connector' => \App\Services\Integrations\Communication\WhatsAppCloudConnector::class, 'help' => 'Lets GeneralLink send real WhatsApp messages (e.g. from the WhatsApp button in the top bar). Get your Phone Number ID and a temporary Access Token from developers.facebook.com — see the step-by-step guide in My Profile if this is your first time.'],
                    // WIRED UP 8 Aug 2026 — Task #85, real connectors for
                    // the GLADE notification-preference channels. Telegram/
                    // LINE/WeChat can each verify a real credential (Test
                    // Connection genuinely works), but actually SENDING to a
                    // specific agent on those three needs a platform-specific
                    // chat/user ID (not a phone number) that agents haven't
                    // linked anywhere yet — see NoticeDeliveryService, which
                    // marks those as SKIPPED with an honest reason rather
                    // than pretending a phone number works. Twilio and
                    // Vonage SMS use real phone numbers, so those two can
                    // fully send today.
                    'telegram' => ['label' => 'Telegram Bot', 'credential_type' => 'BOT_TOKEN', 'connector' => TelegramBotConnector::class, 'help' => 'Lets GeneralLink verify a Telegram bot (create one free via @BotFather on Telegram, copy the token it gives you). Sending to a specific agent still needs that agent to link their Telegram chat first — coming in a later update.'],
                    'line' => ['label' => 'LINE Messaging API', 'credential_type' => 'LINE_TOKEN', 'connector' => LineConnector::class, 'help' => 'Lets GeneralLink verify a LINE Official Account (get a Channel Access Token free from LINE Developers Console). Sending to a specific agent still needs that agent to link their LINE account first — coming in a later update.'],
                    'wechat' => ['label' => 'WeChat Official Account', 'credential_type' => 'WECHAT_CREDS', 'connector' => WeChatConnector::class, 'help' => 'Lets GeneralLink verify a WeChat Official Account (AppID + AppSecret from mp.weixin.qq.com). Sending to a specific agent still needs that agent to link their WeChat account first — coming in a later update.'],
                    'twilio' => ['label' => 'Twilio (SMS)', 'credential_type' => 'TWILIO_CREDS', 'connector' => TwilioSmsConnector::class, 'help' => 'Lets GeneralLink send real SMS via your Twilio account (Account SID + Auth Token from twilio.com/console, plus a Twilio phone number to send from). Real per-SMS cost applies on your Twilio account.'],
                    'vonage' => ['label' => 'Vonage (SMS)', 'credential_type' => 'API_KEY_SECRET', 'connector' => VonageSmsConnector::class, 'help' => 'Lets GeneralLink send real SMS via your Vonage account (API Key + API Secret from dashboard.nexmo.com). Real per-SMS cost applies on your Vonage account.'],
                ],
            ],
            'ocr' => [
                'label' => 'Document OCR', 'icon' => 'ti-scan', 'description' => 'Upgrades the existing sales-transaction document extraction feature.',
                'providers' => [
                    'google_vision' => ['label' => 'Google Cloud Vision', 'credential_type' => 'SERVICE_ACCOUNT', 'connector' => null],
                    'aws_textract' => ['label' => 'AWS Textract', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'azure_form_recognizer' => ['label' => 'Azure Form Recognizer', 'credential_type' => 'API_KEY', 'connector' => null],
                ],
            ],
            'social_media' => [
                'label' => 'Social Media', 'icon' => 'ti-share', 'description' => 'Posting and page management.',
                'providers' => [
                    'facebook' => ['label' => 'Facebook', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'instagram' => ['label' => 'Instagram', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'x' => ['label' => 'X (Twitter)', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'linkedin' => ['label' => 'LinkedIn', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'tiktok' => ['label' => 'TikTok', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'youtube' => ['label' => 'YouTube', 'credential_type' => 'OAUTH2', 'connector' => null],
                ],
            ],
            'email_services' => [
                'label' => 'Email Services', 'icon' => 'ti-mail', 'description' => 'Sending and inbox integration.',
                'providers' => [
                    'gmail' => ['label' => 'Gmail', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'outlook' => ['label' => 'Microsoft Outlook', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'smtp' => ['label' => 'SMTP', 'credential_type' => 'USERNAME_PASSWORD', 'connector' => null],
                    'sendgrid' => ['label' => 'SendGrid', 'credential_type' => 'API_KEY', 'connector' => null],
                    'mailgun' => ['label' => 'Mailgun', 'credential_type' => 'API_KEY', 'connector' => null],
                    'amazon_ses' => ['label' => 'Amazon SES', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                ],
            ],
            'cloud_storage' => [
                'label' => 'Cloud Storage', 'icon' => 'ti-cloud', 'description' => 'File storage and sync.',
                'providers' => [
                    'google_drive' => ['label' => 'Google Drive', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'onedrive' => ['label' => 'OneDrive', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'dropbox' => ['label' => 'Dropbox', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'aws_s3' => ['label' => 'AWS S3', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'azure_blob' => ['label' => 'Azure Blob Storage', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                ],
            ],
            'payment_gateways' => [
                'label' => 'Payment Gateways', 'icon' => 'ti-credit-card', 'description' => 'Collecting and processing payments.',
                'providers' => [
                    'stripe' => ['label' => 'Stripe', 'credential_type' => 'API_KEY', 'connector' => null],
                    'paypal' => ['label' => 'PayPal', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'billplz' => ['label' => 'Billplz', 'credential_type' => 'API_KEY', 'connector' => null],
                    'toyyibpay' => ['label' => 'ToyyibPay', 'credential_type' => 'API_KEY', 'connector' => null],
                    'ipay88' => ['label' => 'iPay88', 'credential_type' => 'MERCHANT_ID', 'connector' => null],
                    'hitpay' => ['label' => 'HitPay', 'credential_type' => 'API_KEY', 'connector' => null],
                    'razorpay' => ['label' => 'Razorpay', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                ],
            ],
            'erp_systems' => [
                'label' => 'ERP Systems', 'icon' => 'ti-building-factory', 'description' => 'Enterprise resource planning.',
                'providers' => [
                    'sap' => ['label' => 'SAP', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'oracle_erp' => ['label' => 'Oracle ERP', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'dynamics_365' => ['label' => 'Microsoft Dynamics 365', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'odoo' => ['label' => 'Odoo', 'credential_type' => 'API_KEY', 'connector' => null],
                    'netsuite' => ['label' => 'NetSuite', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'epicor' => ['label' => 'Epicor', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'custom_erp' => ['label' => 'Custom ERP', 'credential_type' => 'API_KEY', 'connector' => null],
                ],
            ],
            'pos_systems' => [
                'label' => 'POS Systems', 'icon' => 'ti-cash-register', 'description' => 'Point of sale terminals.',
                'providers' => [
                    'square' => ['label' => 'Square', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'clover' => ['label' => 'Clover', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'toast' => ['label' => 'Toast', 'credential_type' => 'API_KEY', 'connector' => null],
                    'cloud_pos' => ['label' => 'Cloud POS (generic)', 'credential_type' => 'API_KEY', 'connector' => null],
                    'custom_pos' => ['label' => 'Custom POS', 'credential_type' => 'API_KEY', 'connector' => null],
                ],
            ],
            'crm_systems' => [
                'label' => 'CRM Systems', 'icon' => 'ti-address-book', 'description' => 'Customer relationship management.',
                'providers' => [
                    'salesforce' => ['label' => 'Salesforce', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'hubspot' => ['label' => 'HubSpot', 'credential_type' => 'API_KEY', 'connector' => null],
                    'zoho_crm' => ['label' => 'Zoho CRM', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'dynamics_crm' => ['label' => 'Microsoft Dynamics CRM', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'custom_crm' => ['label' => 'Custom CRM', 'credential_type' => 'API_KEY', 'connector' => null],
                ],
            ],
            'accounting_systems' => [
                'label' => 'Accounting Systems', 'icon' => 'ti-calculator', 'description' => 'Bookkeeping and invoicing.',
                'providers' => [
                    'quickbooks' => ['label' => 'QuickBooks', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'xero' => ['label' => 'Xero', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'sage' => ['label' => 'Sage', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'autocount' => ['label' => 'AutoCount', 'credential_type' => 'API_KEY', 'connector' => null],
                    'sql_accounting' => ['label' => 'SQL Accounting', 'credential_type' => 'API_KEY', 'connector' => null],
                    'million_accounting' => ['label' => 'Million Accounting', 'credential_type' => 'API_KEY', 'connector' => null],
                ],
            ],
            'ecommerce_platforms' => [
                'label' => 'E-Commerce Platforms', 'icon' => 'ti-shopping-cart', 'description' => 'Online storefronts.',
                'providers' => [
                    'shopify' => ['label' => 'Shopify', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'woocommerce' => ['label' => 'WooCommerce', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'magento' => ['label' => 'Magento', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'bigcommerce' => ['label' => 'BigCommerce', 'credential_type' => 'API_KEY', 'connector' => null],
                    'opencart' => ['label' => 'OpenCart', 'credential_type' => 'API_KEY', 'connector' => null],
                    'prestashop' => ['label' => 'PrestaShop', 'credential_type' => 'API_KEY', 'connector' => null],
                ],
            ],
            'marketplaces' => [
                'label' => 'Online Marketplaces', 'icon' => 'ti-building-store', 'description' => 'Third-party marketplace selling.',
                'providers' => [
                    'shopee' => ['label' => 'Shopee', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'lazada' => ['label' => 'Lazada', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'alibaba' => ['label' => 'Alibaba', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'aliexpress' => ['label' => 'AliExpress', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'taobao' => ['label' => 'Taobao', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'tmall' => ['label' => 'Tmall', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'ebay' => ['label' => 'eBay', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'amazon_marketplace' => ['label' => 'Amazon Marketplace', 'credential_type' => 'OAUTH2', 'connector' => null],
                    'etsy' => ['label' => 'Etsy', 'credential_type' => 'OAUTH2', 'connector' => null],
                ],
            ],
            'hospitality' => [
                'label' => 'Hotel & Hospitality', 'icon' => 'ti-bed', 'description' => 'Property and reservation systems.',
                'providers' => [
                    'pms' => ['label' => 'Property Management System', 'credential_type' => 'API_KEY', 'connector' => null],
                    'crs' => ['label' => 'Central Reservation System', 'credential_type' => 'API_KEY', 'connector' => null],
                    'channel_manager' => ['label' => 'Channel Manager', 'credential_type' => 'API_KEY', 'connector' => null],
                    'booking_engine' => ['label' => 'Booking Engine', 'credential_type' => 'API_KEY', 'connector' => null],
                    'ota' => ['label' => 'Online Travel Agencies (OTA)', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                ],
            ],
            'logistics_delivery' => [
                'label' => 'Logistics & Delivery', 'icon' => 'ti-truck-delivery', 'description' => 'Courier and delivery integration.',
                'providers' => [
                    'dhl' => ['label' => 'DHL', 'credential_type' => 'API_KEY', 'connector' => null],
                    'fedex' => ['label' => 'FedEx', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'ups' => ['label' => 'UPS', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'ninja_van' => ['label' => 'Ninja Van', 'credential_type' => 'API_KEY', 'connector' => null],
                    'jt_express' => ['label' => 'J&T Express', 'credential_type' => 'API_KEY', 'connector' => null],
                    'easyparcel' => ['label' => 'EasyParcel', 'credential_type' => 'API_KEY', 'connector' => null],
                    'lalamove' => ['label' => 'Lalamove', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'grabexpress' => ['label' => 'GrabExpress', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                ],
            ],
            'government_tax' => [
                'label' => 'Government & Tax', 'icon' => 'ti-building-bank', 'description' => 'Regulatory and tax filing.',
                'providers' => [
                    'e_invoicing' => ['label' => 'e-Invoicing', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                    'tax_api' => ['label' => 'Tax APIs', 'credential_type' => 'API_KEY', 'connector' => null],
                    'customs_api' => ['label' => 'Customs APIs', 'credential_type' => 'API_KEY', 'connector' => null],
                    'gov_digital' => ['label' => 'Government Digital Services', 'credential_type' => 'CLIENT_CREDENTIALS', 'connector' => null],
                ],
            ],
            'developer_apis' => [
                'label' => 'Developer APIs', 'icon' => 'ti-code', 'description' => 'Generic and custom connections.',
                'providers' => [
                    'rest_api' => ['label' => 'REST API', 'credential_type' => 'API_KEY', 'connector' => null],
                    'graphql_api' => ['label' => 'GraphQL API', 'credential_type' => 'API_KEY', 'connector' => null],
                    'webhook' => ['label' => 'Webhooks', 'credential_type' => 'WEBHOOK_SECRET', 'connector' => null],
                    'mqtt' => ['label' => 'MQTT', 'credential_type' => 'USERNAME_PASSWORD', 'connector' => null],
                    'websocket' => ['label' => 'WebSocket', 'credential_type' => 'API_KEY', 'connector' => null],
                    'custom_connector' => ['label' => 'Custom Connector', 'credential_type' => 'API_KEY', 'connector' => null],
                ],
            ],
        ];
    }

    public static function provider(string $category, string $provider): ?array
    {
        $cat = self::categories()[$category] ?? null;
        return $cat['providers'][$provider] ?? null;
    }

    public static function connectorFor(string $category, string $provider): ?IntegrationConnectorInterface
    {
        $meta = self::provider($category, $provider);
        $class = $meta['connector'] ?? null;
        return $class ? app($class) : null;
    }

    // NEW 4 Aug 2026 — Chris asked to be able to save credentials for
    // EVERY category today, not just the ones with a working Test
    // Connection so far. Different providers need different credential
    // shapes, so this collapses the 8 credential_type values used in
    // categories() down to the 2 shapes the connect form actually
    // renders: one secret field, or a labelled pair. Both are stored
    // encrypted; nothing here is ever shown back in plain text.
    public static function fieldShape(string $credentialType): array
    {
        return match ($credentialType) {
            'CLIENT_CREDENTIALS', 'OAUTH2' => ['type' => 'pair', 'label1' => 'Client ID', 'label2' => 'Client Secret', 'field1' => 'client_id', 'field2' => 'client_secret'],
            // NEW 6 Aug 2026 — WhatsApp's Cloud API needs TWO things
            // together (which phone number, and the token that proves you
            // own it) — reuses the same pair storage as Client
            // Credentials, just relabeled to match what Meta's own screen
            // actually calls these two values.
            'WHATSAPP_CLOUD' => ['type' => 'pair', 'label1' => 'Phone Number ID', 'label2' => 'Access Token', 'field1' => 'client_id', 'field2' => 'client_secret'],
            'USERNAME_PASSWORD' => ['type' => 'pair', 'label1' => 'Username', 'label2' => 'Password', 'field1' => 'client_id', 'field2' => 'client_secret'],
            'MERCHANT_ID' => ['type' => 'pair', 'label1' => 'Merchant ID', 'label2' => 'Secret Key', 'field1' => 'client_id', 'field2' => 'client_secret'],
            'BOT_TOKEN' => ['type' => 'single', 'label1' => 'Bot Token', 'field1' => 'api_key'],
            'WEBHOOK_SECRET' => ['type' => 'single', 'label1' => 'Webhook Secret', 'field1' => 'api_key'],
            // NEW 8 Aug 2026 — Task #85, nicer field labels matching each
            // provider's own terminology instead of the generic defaults.
            'LINE_TOKEN' => ['type' => 'single', 'label1' => 'Channel Access Token', 'field1' => 'api_key'],
            'WECHAT_CREDS' => ['type' => 'pair', 'label1' => 'AppID', 'label2' => 'AppSecret', 'field1' => 'client_id', 'field2' => 'client_secret'],
            'TWILIO_CREDS' => ['type' => 'pair', 'label1' => 'Account SID', 'label2' => 'Auth Token', 'field1' => 'client_id', 'field2' => 'client_secret'],
            'API_KEY_SECRET' => ['type' => 'pair', 'label1' => 'API Key', 'label2' => 'API Secret', 'field1' => 'client_id', 'field2' => 'client_secret'],
            'SERVICE_ACCOUNT' => ['type' => 'single', 'label1' => 'Service Account JSON (paste the whole file)', 'field1' => 'api_key', 'textarea' => true],
            default => ['type' => 'single', 'label1' => 'API Key', 'field1' => 'api_key'], // API_KEY and anything unrecognized
        };
    }
}
