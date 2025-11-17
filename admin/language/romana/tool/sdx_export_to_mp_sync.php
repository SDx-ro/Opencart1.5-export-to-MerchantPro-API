<?php

/* v1.4 RO language SDxExportToMPSync */

// Heading
$_['heading_title']       = 'Export catre MerchantPro si Sincronizare';

// Text
$_['text_success_saved']            = 'Setarile au fost salvate.';
$_['text_export_placeholder']       = 'Export placeholder: not implemented yet.';
$_['text_no_results']               = 'Nu sunt rezultate.';
//$_['text_pagination']             = 'Afiseaza {start} la {end} din {total} ({pages} Pagini)';
$_['text_home']                     = 'Acasa';
$_['text_enabled']                  = 'Activ';
$_['text_disabled']                 = 'Inactiv';
$_['text_filter_all']               = '-- Toate --';
$_['text_filter_all_categories']    = '-- Toate Categoriile --';

// Tabs
$_['tab_products']      = 'Produse';
$_['tab_categories']    = 'Categorii';
$_['tab_settings']      = 'Setari / API';

// Entries / Columns / Buttons
$_['entry_product_status']      = 'Stare Produs:';
$_['entry_product_category']    = 'Produse Filtrate dupa Categorii:';
$_['entry_api_name']            = 'API Name:';
$_['entry_api_url']             = 'API URL:';
$_['entry_api_key']             = 'API Key:';
$_['entry_api_secret']          = 'API Secret:';
$_['entry_feed_simple']         = 'Feed Simplu MP<br> (produse simple/variabile)';
$_['entry_feed_variants']       = 'Feed Variatiuni MP<br> (variatiuni/variante produs)';
$_['button_save']               = 'Salveaza';
$_['button_export']             = 'Export';

// Strings for MP feed update
$_['button_update_mp_feed'] = 'Actualizare Produse MerchantPro (Feed Consolidat)';
$_['text_current_mp_feed']  = 'Produse MerchantPro (Feed Consolidat)';
$_['text_no_mp_feed']       = 'NU este disponibil Feed-ul Consolidat pentru Produse MerchantPro! <br> Click <b>'.$_['button_update_mp_feed'].'</b> <br> pentru a descarca si consolida feed-urile.';
$_['text_mp_feed_update']   = 'Actualizare necesara!';
$_['text_mp_feed_updated']  = 'S-a actualizat Feed-ul Consolidat pentru Produse MerchantPro';

$_['error_mp_feed_update']      = 'A esuat actualizarea Feed-ului Consolidat pentru Produse MerchantPro.';
$_['error_no_file_specified']   = 'Nici un fisier specificat pentru descarcare!';
$_['error_invalid_file']        = 'Fisier invalid selectat pentru descarcare!';
$_['error_file_not_found']      = 'Fisierul solicitat nu poate fi gasit!';

// Columns (labels)
$_['col_product']       = 'Produs';
$_['col_category']      = 'Categorie';
$_['col_name']          = 'Nume';
$_['col_model']         = 'Model / SKU / EAN';
$_['col_categories']    = 'Categorii';
$_['col_status']        = 'Stare / Stoc';
$_['col_prices']        = 'Preturi';
$_['col_options']       = 'Optiuni';

$_['col_mp_status']     = 'MerchantPro Status';

$_['mp_status_in_mp']               = 'In MP';
$_['mp_status_in_mp_by_sku']        = 'In MP (prin SKU)';
$_['mp_status_out_of_sync']         = 'Nesincronizat';
$_['mp_status_price_stock_diff']    = 'Diferenta Pret/Stoc';
$_['mp_status_collision']           = 'Coliziune';
$_['mp_status_missing']             = 'Lipsa in MP';
$_['mp_status_no_feed']             = 'No feed';

// Errors
$_['error_permission']      = 'Atentie: Nu aveti permisiunea sa modificati!';
$_['error_api_required']    = 'MerchantPro API: adresa URL (website), Cheia (utilizator) si Secret (parola) sunt necesare!';

$_['text_json_prepared']    = '%s JSON with %d items -> %s';
$_['error_json_prepare']    = 'Failed preparing JSON file.';
$_['error_no_json_to_push'] = 'No JSON file found to push for this mode.';
$_['text_push_done']        = 'Push %s: OK=%d, Fail=%d, Skipped=%d (file: %s)';
$_['error_push_failed']     = 'Push failed.';
$_['text_purged_files']     = 'Deleted %d MP export files from logs.';

// Category tab columns
$_['col_mp_category']         = 'Categorii MerchantPro';
$_['col_mp_category_status']  = 'Starea Sincronizarii cu MP';

// Category sync status labels
$_['text_mp_cat_status_ok']            = 'OK (sincronizat)';
$_['text_mp_cat_status_only_oc']       = 'Numai in OpenCart (pentru adaugare -> POST)';
$_['text_mp_cat_status_only_mp']       = 'Numai in MerchantPro (pentru stergere -> DELETE)';
$_['text_mp_cat_status_patch_name']    = 'Numele difera (actualizare -> PATCH)';
$_['text_mp_cat_status_patch_parent']  = 'Parintele difera (actualizare -> PATCH)';
$_['text_mp_cat_status_patch_status']  = 'Starea difera (actualizare -> PATCH)';
$_['text_mp_cat_status_patch_complex'] = 'Nume/Parinte/Stare difera (actualizare -> PATCH)';

// extra
$_['text_categories_tab_info'] = 'Lista Categorii';

$_['button_get_mp_categories'] = 'Actualizare Categorii MerchantPro';

$_['button_get_mp_taxononies'] = 'Actualizeaza Taxonomii MerchantPro (taxe, unitati de masura, etc)';

$_['text_get_mp_taxononies_help'] = 'Preia Taxonomii MerchantPro (taxe, unitati de masura, etc) via API <br> si salveaza-le in fisiere JSON in system/logs/.';

?>