<?php

/* v1.5 EN language SDxExportToMPSync */

// Heading
$_['heading_title']       = 'Export to MerchantPro and Sync';

// Text
$_['text_success_saved']            = 'Settings saved.';
$_['text_export_placeholder']       = 'Export placeholder: not implemented yet.';
$_['text_no_results']               = 'No results found.';
//$_['text_pagination']             = 'Showing {start} to {end} of {total} ({pages} Pages)';
$_['text_home']                     = 'Home';
$_['text_enabled']                  = 'Enabled';
$_['text_disabled']                 = 'Disabled';
$_['text_filter_all']               = '-- All --';
$_['text_filter_all_categories']    = '-- All Categories --';

// Tabs
$_['tab_products']      = 'Products';
$_['tab_categories']    = 'Categories';
$_['tab_settings']      = 'Settings / API';

// Entries / Columns / Buttons
$_['entry_product_status']      = 'Product Status:';
$_['entry_product_category']    = 'Products Filtered by Categories:';
$_['entry_api_name']            = 'API Name:';
$_['entry_api_url']             = 'API URL:';
$_['entry_api_key']             = 'API Key:';
$_['entry_api_secret']          = 'API Secret:';
$_['entry_feed_simple']         = 'MP Feed Simple<br> (simple/variable products)';
$_['entry_feed_variants']       = 'MP Feed Variants<br> (variant/variation products)';
$_['button_save']               = 'Save';
$_['button_export']             = 'Export';

// Strings for MP feed update
$_['button_update_mp_feed'] = 'Update MerchantPro Products (Consolidated Feed)';
$_['text_current_mp_feed']  = 'MerchantPro Products (Consolidated Feed)';
$_['text_no_mp_feed']       = 'Consolidated Feed for MerchantPro Products is not available! <br> Click <b>'.$_['button_update_mp_feed'].'</b> <br> to download and merge feeds.';
$_['text_mp_feed_update']   = 'Update needed!';
$_['text_mp_feed_updated']  = 'Consolidated Feed for MerchantPro Products was updated';

$_['error_mp_feed_update']      = 'Failed updating the Consolidated Feed for MerchantPro Products. Check the API settings for Feeds! Possible other error(s)...';
$_['error_no_file_specified']   = 'No file specified for download!';
$_['error_invalid_file']        = 'Invalid file selected for download!';
$_['error_file_not_found']      = 'The requested file could not be found!';

// Columns (labels)
$_['col_product']       = 'Product';
$_['col_category']      = 'Category';
$_['col_name']          = 'Name';
$_['col_model']         = 'Model / SKU / EAN';
$_['col_categories']    = 'Categories';
$_['col_status']        = 'Status / Stock';
$_['col_prices']        = 'Prices';
$_['col_options']       = 'Options';

$_['col_mp_status']     = 'MerchantPro Status';

$_['mp_status_in_mp']               = 'In MP';
$_['mp_status_in_mp_by_sku']        = 'In MP (by SKU)';
$_['mp_status_out_of_sync']         = 'Out of Sync';
$_['mp_status_price_stock_diff']    = 'Price/Stock Difference';
$_['mp_status_collision']           = 'Collision';
$_['mp_status_missing']             = 'Missing in MP';
$_['mp_status_no_feed']             = 'No feed';

// Errors
$_['error_permission']      = 'Warning: You do not have permission to modify this!';
$_['error_api_required']    = 'MerchantPro API URL (website), Key (user) and Secret (password) are required!';

$_['text_json_prepared']    = '%s JSON with %d items -> %s';
//$_['error_json_prepare']    = 'Failed preparing JSON file.';
//$_['error_no_json_to_push'] = 'No JSON file found to push for this mode.';
//$_['text_push_done']        = 'Push %s: OK=%d, Fail=%d, Skipped=%d (file: %s)';
//$_['error_push_failed']     = 'Push failed.';
//$_['text_purged_files']     = 'Deleted %d MP export files from logs.';

// Category tab columns
$_['col_mp_categories']         = 'MP Categories';
$_['col_mp_sync_status']  = 'MP Sync status';

// MP sync actions
$_['button_mp_force_patch']  = 'Force Update to MP (PATCH)';
$_['button_mp_force_post']   = 'Force Add to MP (POST)';
$_['button_mp_force_delete'] = 'Force DELETE from MP';

// Extra category sync tab
$_['tab_mp_categories_delete']        = 'MP Categories to Delete';
$_['text_mp_categories_delete_info']  = 'MerchantPro categories that exist in MP but not in OpenCart (candidates for DELETE).';

// Optional columns (if you want more specific headers)
$_['col_mp_category_id']   = 'MP Category ID';
$_['col_mp_category_path'] = 'MP Category';
$_['col_action']           = 'Action';

// Category sync status labels
$_['text_mp_cat_status_ok']            = 'OK (in sync)';
$_['text_mp_cat_status_only_oc']       = 'Only in OpenCart';
$_['text_mp_cat_status_only_mp']       = 'Only in MerchantPro';
$_['text_mp_cat_status_patch_name']    = 'Name differs';
$_['text_mp_cat_status_patch_parent']  = 'Parent differs';
$_['text_mp_cat_status_patch_status']  = 'Status differs';
$_['text_mp_cat_status_patch_complex'] = 'Name/Parent/Status differ';

// extra
$_['text_categories_tab_info'] = 'Category listing.';

$_['button_get_mp_products'] = 'Update MerchantPro Products';

$_['button_get_mp_categories'] = 'Update MerchantPro Categories';

$_['button_get_mp_taxonomies'] = 'Get MerchantPro Taxonomies';

$_['text_get_mp_taxonomies_help'] = 'Get MerchantPro Taxonomies (taxes, measurement units, etc) via API <br> and store them in JSON files under system/logs/.';

?>