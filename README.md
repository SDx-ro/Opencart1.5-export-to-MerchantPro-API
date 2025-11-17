📦 SDx Export to MerchantPro API
Module for OpenCart 1.5.x → MerchantPro API product synchronization
This tool provides a structured way to export/sync OpenCart 1.5.x product, category, attribute, and auxiliary data into MerchantPro (MP) using the MP API.
It is designed for a custom and modernized OpenCart 1.5.5 installation upgraded to PHP 8.3, with a legacy-preserved and customized jQuery 1.7 environment.

🚀 Features
Extracts data from OpenCart 1.5.x (products, categories, attributes, etc.)
Loads MerchantPro product data through XLSX feeds (feeds URLs stored beside API settings)
Merges & consolidates the MP feeds (simple & variable vs. variants) into a unified product dataset
Generates a JSON cache file containing MP products
MP products with ext_ref (identifiable)
MP products without ext_ref (susceptible to deletion)
Provides admin-side UI for monitoring, triggering sync, and reviewing data
Stores API configuration using standard OC setting table mechanisms
Fully compatible with a heavily customized OC1.5 on PHP 8.3

📁 OpenCart Module Structure (Standard OC 1.5 Routing)
The module follows the classic OpenCart 1.5.x admin routing pattern:

admin/
├── controller/
│   └── tool/
│       └── sdx_export_to_mp_sync.php
├── language/
│   ├── english/
│   │   └── tool/
│   │       └── sdx_export_to_mp_sync.php
│   └── romana/
│       └── tool/
│           └── sdx_export_to_mp_sync.php
├── model/
│   └── tool/
│       └── sdx_export_to_mp_sync.php
└── view/
    └── template/
        └── tool/
            └── sdx_export_to_mp_sync.tpl

⚙️ MerchantPro API Configuration
MerchantPro API settings are stored using the native OC 1.5 setting table conventions.

// Load settings & derive store slug
$this->load->model('setting/setting');
$settings = $this->model_setting_setting->getSetting('sdx_export_to_mp_sync');

// Grouped API settings
$api = isset($settings['sdx_export_to_mp_sync_api']) 
    ? $settings['sdx_export_to_mp_sync_api'] 
    : array();

The $api settings array includes:
Key	- Description
$api['mp_api_url']	- MerchantPro API base URL (e.g., https://www.okled.ro)
$api['mp_api_name']	- Informational name for the connection
$api['mp_api_key']	- API username
$api['mp_api_secret']	- API password
$api['mp_feed_simple']	- URL to XLSX feed for simple/variable products (no variants)
$api['mp_feed_variants']	- URL to XLSX feed for product variants

Additionally: sdx_export_to_mp_sync_module
Stored in the setting table (group sdx_export_to_mp_sync)
Represents whether MerchantPro sync is enabled
May be used as a feature toggle for additional future behaviors

📄 How the Module Works
1. Fetch XLSX Feeds from MerchantPro
Two MP-generated XLSX datasets are retrieved:
Simple + variable products feed
Variant products feed

2. Parse & Combine Data
The tool analyzes, merges, and normalizes both XLSX sources into one consolidated dataset.

3. Generate JSON Cache
A JSON cache file of MP products is created, splitting entries into:
Products with ext_ref → can be matched with OpenCart products
Products without ext_ref → unidentifiable, potentially removable or problematic

4. Sync / Export Logic
Handled within the controller and model:
Comparison between OC & MP datasets
Identification of new, updated, or removable MP products
Data mapped according to MP’s API requirements
Admin interface (.tpl) provides visualization & controls.

🏗️ Requirements
OpenCart 1.5.x (customized)

PHP 8.3 compatible environment
(This module assumes you are running a modernized OC1.5 with deprecated code already upgraded.)

cURL enabled

XLSX parsing library compatible with PHP 8.x
// require libraries
require_once(DIR_SYSTEM . '/library/SimpleXLSX/SimpleXLSX.php');
require_once(DIR_SYSTEM . '/library/SimpleXLSXGen/SimpleXLSXGen.php');

🔧 Installation
Upload the admin folder to your OpenCart installation:
your-store/admin/

Navigate to:
Admin → Tools → SDx Export to MerchantPro Sync

Configure MerchantPro API credentials:
API URL
API Name
API Key & Secret
XLSX feed links
Save settings — they are stored under:
setting.group = 'sdx_export_to_mp_sync'
Begin synchronization using the admin interface.

📌 Notes
This module interacts with MerchantPro exclusively through its official API endpoints.
It does not modify core OpenCart data unless explicitly implemented in controller/model logic.
The system is optimized for large product catalogs and repeated exports.

📜 License
This project is proprietary and intended for specific deployment.
Redistribution is not permitted unless explicitly authorized.

🤝 Contributions

Although this repository is currently focused on internal development, contributions (PRs, issues, improvements) may be considered in the future.

📬 Support

For code review, enhancements, or debugging assistance, please contact the project maintainer.
