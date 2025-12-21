<?php echo $header; ?>

<!-- v1.5.3 template SDxExportToMPSync -->

<style type="text/css">
/* Small helper styles (OC1.5 safe) */
.sdx-table .prod-cell { display:flex; align-items:center; gap:8px; }
.sdx-table .prod-thumb { width:40px; height:40px; border:1px solid #ddd; border-radius:3px; overflow:hidden; flex:0 0 40px; }
.sdx-table .prod-thumb img { width:40px; height:40px; object-fit:cover; display:block; }
.sdx-table .prod-meta { line-height:1.25; font-size:11px; color:#555; }
.sdx-table .prod-id { font-size:11px; color:#333; margin-top:2px; }
.sdx-table .nowrap { white-space:nowrap; }
.sdx-table .nowrap a { margin-right:6px; /*text-decoration:none;*/ }
.sdx-table .name-block { line-height:1.3; }
.sdx-table .name-block .small { font-size:11px; color:#666; }
.sdx-table .cats { min-width:450px; line-height:1.2; font-size:10px; color:#444; }
.sdx-table .status-block { line-height:1.2; font-size:10px; min-width: 140px; }
.sdx-table .prices { line-height:1.2; font-size:10px; min-width: 140px; }
.sdx-table .prices .base.has-special { text-decoration: line-through rgba(255, 0, 0, 0.5); }
.sdx-table .options { /*min-width:150px;*/ line-height:1.2; font-size:10px; color:#444; }
.sdx-table .muted { color:#888; }
.link { background-color: #003A88; color: lightgray; border-radius: .45em; text-decoration: none; }
.link:hover { background-color: darkgreen; }

.sdx-table .stack > div { margin-bottom:2px; }

.row-variant { background: antiquewhite; }
.type-label { font-size:10px; padding:2px 4px; border-radius:2px; background:#eee; /*margin-right:6px;*/ font-weight:bold; }

/* MP sync badge */
.mp-sync-badge { display:inline-block; padding:3px 6px; border-radius:3px; font-size:11px; font-weight:bold; }
.mp-sync-in_mp { background:#dff0d8; color:#3c763d; }
.mp-sync-missing { background:#f2dede; color:#a94442; }
.mp-sync-out_of_sync { background:#fcf8e3; color:#8a6d3b; }
.mp-sync-collision { background:#ffe0e0; color:#a40000; }
.mp-sync-no_feed { background:#eee; color:#666; }
.mp-sync-in_mp_by_sku { background:#eff0e8; color:#3d763e; }

/* Reuse badge colors for category sync codes */
.mp-sync-ok { background:#dff0d8; color:#3c763d; } 
.mp-sync-only_oc,
.mp-sync-only_mp { background:#f2dede; color:#a94442; }
.mp-sync-patch_name,
.mp-sync-patch_parent,
.mp-sync-patch_status,
.mp-sync-patch_complex { background:#fcf8e3; color:#8a6d3b; }
.mp-sync-api_error { background:#eee; color:#666; }

/* MP categories sync badge (OC vs MP categories) */
/*
.mp-cat-sync-ok { background:#dff0d8; color:#3c763d; }
.mp-cat-sync-only_oc,
.mp-cat-sync-only_mp { background:#f2dede; color:#a94442; }
.mp-cat-sync-patch_name,
.mp-cat-sync-patch_parent,
.mp-cat-sync-patch_status,
.mp-cat-sync-patch_complex { background:#fcf8e3; color:#8a6d3b; }
.mp-cat-sync-api_error { background:#eee; color:#666; }
*/
</style>

<div id="content">
    <div class="breadcrumb">
        <?php foreach ($breadcrumbs as $breadcrumb) { ?>
            <?php echo $breadcrumb['separator']; ?><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a>
        <?php } ?>
    </div>
    
    <?php 
    if ($updatefeeds || $updatempprods || $updatempcats || $updatemptaxes) {
        echo '<div class="warning">'.$text_mp_update_needed.'<br>';
        if ($updatefeeds) {
            echo '&nbsp; -> '. $text_current_mp_feed.'<br>';
        }
        if ($updatempprods) {
            echo '&nbsp; -> '. $this->language->get('button_get_mp_products').'<br>';
        }
        if ($updatempcats) {
            echo '&nbsp; -> '. $col_mp_categories.'<br>';
        }
        if ($updatemptaxes) {
            echo '&nbsp; -> '. $button_get_mp_taxonomies.'<br>';
        }
        echo '</div>';
    }
    ?>
    
    <?php if ($error_warning) { ?>
    <div class="warning"><?php echo $error_warning; ?></div>
    <?php } ?>

    <?php if ($success) { ?>
    <div class="success"><?php echo $success; ?></div>
    <?php } ?>
    
    <div class="box">
        
        <div class="heading">
            <h1>
                <img src="view/image/review.png" alt="<?php echo $heading_title; ?>"> <?php echo $heading_title; ?>
                
            </h1>
            <div class="buttons">
                <a href="https://app.merchantpro.com/?locale=ro_RO" target="_blank" class="button link">MerchantPro Dashboard</a> &nbsp; 
                <?php echo isset($api['mp_api_url']) ? '<a href="'.$api['mp_api_url'].'" target="_blank" class="button link">'.(isset($api['mp_api_name']) ? $api['mp_api_name'] : '').' -> MP Website</a> &nbsp; ' : ''; ?>
                
                <a href="<?php echo $export; ?>" class="button link"><?php echo $button_export; ?></a> &nbsp; 
            </div>
        </div>
        
        <div class="content" style="margin-bottom: 0; min-height: auto; background: lightyellow; padding: .5em;">
            
            <!-- MerchatPro Taxonomies from API -->
            <div style="display: inline-table; padding: .25em; border-right: .1em solid green;">
                <?php echo $mptaxes_source . ' <br> <small>' . $mptaxdate . '</small> <br> => <strong>'.str_replace(DIR_LOGS, '', $mptaxes_file).'</strong>'; ?>
                <br>
                <a href="<?php echo $this->url->link('tool/sdx_export_to_mp_sync/mpGetTaxonomies', 'token=' . $token, 'SSL'); ?>" class="button link">
                    <span class="<?php echo ($updatemptaxes ? 'warning' : 'attention'); ?>" style="padding: .25em 1.5em;"></span> &nbsp; <?php echo $button_get_mp_taxonomies; ?>
                </a>
                <br><?php echo $this->language->get('text_get_mp_taxonomies_help'); ?>
            </div>
            
            <!-- MerchatPro Categories from API -->
            <div style="display: inline-table; padding: .25em; border-right: .1em solid green;">
                <?php echo $mpcategories_source . ' <br> <small>' . $mpcatsdate . '</small> <br> => <strong>'.str_replace(DIR_LOGS, '', $mpcategories_file).'</strong>'; ?>
                <br>
                <a href="<?php echo $this->url->link('tool/sdx_export_to_mp_sync/buildMPallCategoriesCache', 'token=' . $token, 'SSL'); ?>" class="button link">
                    <span class="<?php echo ($updatempcats ? 'warning' : 'attention'); ?>" style="padding: .25em 1.5em;"></span> &nbsp; <?php echo $this->language->get('button_get_mp_categories'); ?>
                </a>
            </div>
            
            <!-- MerchatPro Products from API -->
            <div style="display: inline-table; padding: .25em; border-right: .1em solid green;">
                <?php echo $mpproducts_source . ' <br> <small>' . $mpprodsdate . '</small> <br> => <strong>'.str_replace(DIR_LOGS, '', $mpproducts_file).'</strong>'; ?>
                <br>
                <a href="<?php echo $this->url->link('tool/sdx_export_to_mp_sync/buildMPallProductsCache', 'token=' . $token, 'SSL'); ?>" class="button link">
                    <span class="<?php echo ($updatempprods ? 'warning' : 'attention'); ?>" style="padding: .25em 1.5em;"></span> &nbsp; <?php echo $this->language->get('button_get_mp_products'); ?> (from api)
                </a>
                <br><br>
                <a href="<?php echo $this->url->link('tool/sdx_export_to_mp_sync/buildMPallProductsCache', 'token=' . $token . '&force_api=false', 'SSL'); ?>" class="button link">
                    <span class="<?php echo ($updatempprods ? 'warning' : 'attention'); ?>" style="padding: .25em 1.5em;"></span> &nbsp; <?php echo $this->language->get('button_get_mp_products'); ?> (from cache)
                </a>
            </div>
            
            <!-- MerchatPro Categories from XLSX Consolidated Feed -->
            <div style="display: inline-table; padding: .25em; border-right: .1em solid green;">
                <?php if (!empty($mp_export_consolidated_file)) { ?>
                <?php echo $text_current_mp_feed.' <br> <small>'.$feeddate.'</small> <br> => <strong>'.$mp_export_consolidated_file.'</strong>'; ?>
                <?php } else { ?>
                <em><?php echo $text_no_mp_feed; ?></em> &nbsp; 
                <?php } ?>
                <br>
                <a href="<?php echo $this->url->link('tool/sdx_export_to_mp_sync/updateConsolidatedMPfeed', 'token=' . $token, 'SSL'); ?>" class="button link" style="padding: .25em;">
                    <span class="<?php echo ($updatefeeds ? 'warning' : 'attention'); ?>" style="padding: .25em 1.5em;"></span> &nbsp; <?php echo $button_update_mp_feed; ?>
                </a>
                
                <?php if (!empty($mp_export_consolidated_file)) { ?>
                <br><br>
                <a href="index.php?route=tool/sdx_export_to_mp_sync/mpXLSXdownload&token=<?php echo $token; ?>&file=<?php echo urlencode($mp_export_consolidated_file); ?>" class="button link" style="padding: .25em;">
                    <?php echo 'Download: '.$mp_export_consolidated_file; ?>
                </a>
                <?php } ?>
                
                <?php if (!empty($mp_export_simple_file)) { ?>
                <br>
                <small>
                    <a href="index.php?route=tool/sdx_export_to_mp_sync/mpXLSXdownload&token=<?php echo $token; ?>&file=<?php echo urlencode($mp_export_simple_file); ?>" class="link">
                        <?php echo 'Download: '.$mp_export_simple_file; ?>
                    </a>
                </small>
                <?php } ?>
                
                <?php if (!empty($mp_export_variants_file)) { ?>
                <br>
                <small>
                    <a href="index.php?route=tool/sdx_export_to_mp_sync/mpXLSXdownload&token=<?php echo $token; ?>&file=<?php echo urlencode($mp_export_variants_file); ?>" class="link">
                        <?php echo 'Download: '.$mp_export_variants_file; ?>
                    </a>
                </small>
                <?php } ?>
            </div>
            
        </div>
        
        <div class="content sdx-table">
            
            <div id="tabs" class="htabs">
                <a href="#tab-products"><?php echo $tab_products; ?></a>
                <a href="#tab-categories"><?php echo $tab_categories; ?></a>
                <a href="#tab-mp-categories-delete"><?php echo $tab_mp_categories_delete; ?></a>
                <a href="#tab-settings"><?php echo $tab_settings; ?></a>
            </div>
            
            <div id="tab-products">
                <table class="form" style="width: fit-content; margin-bottom: 0;">
                    <tr>
                        <td>
                            <label><?php echo $entry_product_status; ?></label>
                            <select id="product_status">
                                <option value="" <?php echo ($product_status == '' ? 'selected' : ''); ?> ><?php echo $text_filter_all; ?></option>
                                <option value="1" <?php echo ($product_status == '1' ? 'selected' : ''); ?> ><?php echo $text_enabled; ?></option>
                                <option value="0" <?php echo ($product_status ==  '0' ? 'selected' : ''); ?> ><?php echo $text_disabled; ?></option>
                            </select>
                        </td>
                        <td>
                            <label><?php echo $entry_product_category; ?></label><br>
                            <select id="product_category" multiple size="8" style="min-width:300px;">
                                <option value="" <?php echo (empty($product_category) ? 'selected' : ''); ?> ><?php echo $text_filter_all_categories; ?></option>
                                <?php foreach ($categories as $cat) { ?>
                                <?php $sel = ($product_category && in_array($cat['category_id'], explode('-', $product_category))) ? 'selected' : ''; ?>
                                <!-- <optgroup label="<?php echo $cat['name']; ?>"></optgroup> -->
                                <option value="<?php echo $cat['category_id']; ?>" <?php echo $sel; ?> ><?php echo $cat['path']; ?></option>
                                
                                <?php } ?>
                            </select>
                            <div class="">CTRL/SHIFT/CTRL+SHIFT for Multiple-Select</div>
                        </td>
                    </tr>
                </table>
                
                <div class="pagination"><?php echo $pagination; ?></div>
                
                <div class="list" style="width: 99.9%;">
                    
                    <input type="hidden" name="selected_json" id="selected_json" value="">
                    
                    <div id="export_progress" style="display:none; margin:10px 0;">
                        <div id="progress_bar" style="background:#3c9; height:20px; width:0%; color:#fff; text-align:center;">0%</div>
                    </div>
                    
                    <a class="button link" id="start-export" style="float: right;"><?php echo $button_export; ?> Selected</a>
                    
                    <table class="list">
                        <thead>
                            <tr>
                                <td class="left"><?php echo $col_product; ?></td>
                                <td class="left"><?php echo $col_name .' / '. $col_model; ?></td>
                                <td class="left"><?php echo $col_categories; ?></td>
                                <td class="left"><?php echo $col_status; ?></td>
                                <td class="left">
                                    <?php echo $col_mp_status; ?><hr>
                                    Select All <input type="checkbox" id="select_all_products">
                                </td>
                                <td class="left"><?php echo $col_prices; ?></td>
                                <td class="left"><?php echo $col_options; ?></td>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($products) { foreach ($products as $p) { ?>
                                <tr>
                                    <td class="left">
                                        <div class="prod-cell <?php echo ($p['product_type'] == 'variant' ? 'row-variant' : ''); ?>">
                                            <span class="type-label"><?php echo $p['product_type']; ?></span>
                                            <?php if (!empty($p['image'])) { ?>
                                                <div class="prod-thumb"><img src="<?php echo $p['image']; ?>" alt="<?php echo $p['name']; ?>"></div>
                                            <?php } ?>
                                            <div class="prod-meta">
                                                <div class="nowrap">Product ID: <strong><?php echo $p['product_id']; ?></strong></div>
                                                <div class="nowrap">Product Base ID: <strong><?php echo $p['product_id_base']; ?></strong></div>
                                                <div class="nowrap">
                                                    <a href="<?php echo $p['view_product']; ?>" target="_blank">view</a>
                                                    >> OC << &nbsp; 
                                                    <a href="<?php echo $p['edit_product']; ?>" target="_blank">edit</a>
                                                </div>
                                                <div class="prod-id">
                                                    <small>oc ext_ref: <strong><?php echo $p['ext_ref']; ?></strong></small>
                                                    <?php if (!empty($p['mp_product'])) { ?>
                                                    <br>
                                                    <i><small>mp ext_ref: <strong><?php echo $p['mp_product']['ext_ref']; ?></strong></small></i>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="left">
                                        <div class="name-block">
                                            <div>
                                                <strong><?php echo $p['name']; ?></strong>
                                            </div>
                                            <div class="small">
                                                <?php if ($p['model'] != '') { ?><span class="nowrap">Model: <strong><?php echo $p['model']; ?></strong></span><?php } ?>
                                                <?php if ($p['product_type'] == 'variant') { ?><br> <span class="nowrap">(<small>Model_Base: <strong><?php echo $p['model_base']; ?></strong></small>)</span><?php } ?>
                                                <?php if ($p['sku'] != '') { ?> <br> &nbsp; <span class="nowrap">SKU: <strong><?php echo $p['sku']; ?></strong></span><?php } ?>
                                                <?php if ($p['product_type'] == 'variant' && $p['sku_base'] != '') { ?><br> &nbsp; <span class="nowrap">(<small>SKU_Base: <strong><?php echo $p['sku_base']; ?></strong></small>)</span><?php } ?>
                                                <?php if ($p['mpn'] != '') { ?> &nbsp; <span class="nowrap">MPN: <strong><?php echo $p['mpn']; ?></strong></span><?php } ?>
                                            </div>
                                            <?php if (!empty($p['mp_product'])) { ?>
                                            <hr>
                                            <div>
                                                <i><strong><?php echo $p['mp_product']['name']; ?></strong></i>
                                            </div>
                                            <div class="small">
                                                <span class="nowrap"><i>SKU: <strong><?php echo $p['mp_product']['sku']; ?></strong></i></span> &nbsp; 
                                                <span class="nowrap"><i>( <strong><?php echo $p['mp_product']['type']; ?></strong> )</i></span>
                                                
                                            </div>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    
                                    <td class="left">
                                        <div class="cats">
                                            <?php if (!empty($p['categories_paths'])) { ?>
                                                <ol style="padding-inline-start: .55em;">
                                                <?php foreach ($p['categories_paths'] as $cp) { ?>
                                                    <li class="stack"><?php echo $cp; ?></li>
                                                <?php } ?>
                                                </ol>
                                            <?php } else { ?>
                                                <span class="muted"><?php echo $p['categories_list'] ? nl2br($p['categories_list']) : '—'; ?></span>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    
                                    <td class="left">
                                        <div class="status-block">
                                            <strong><?php echo ($p['status'] ? $text_enabled : $text_disabled); ?></strong><br>
                                            Stock: <strong><?php echo $p['quantity']; ?></strong><br>
                                            Stock status: <?php echo $p['stock_status_text'] ? $p['stock_status_text'] : '<span class="muted">n/a</span>'; ?> <small>(ID: <?php echo $p['stock_status_id']; ?>)</small>
                                        </div>
                                    </td>
                                    
                                    <td class="center">
                                        <div class="status-block">
                                            
                                            <span class="mp-sync-badge mp-sync-<?php echo $p['mp_sync_status_code']; ?>">
                                                <?php echo htmlspecialchars($p['mp_sync_status']); ?> &nbsp; 
                                                
                                                <?php if (!empty($p['mp_product'])) { ?>
                                                <span class="nowrap">MP ID: <?php echo $p['mp_product']['id']; ?></span>
                                                <?php } ?>
                                                
                                            </span>
                                            
                                            <?php if (!empty($p['mp_sync_issues'])) { ?>
                                            <div class="small muted" style="text-align: left;">Issues<br> <?php echo $p['mp_sync_issues']; ?></div>
                                            <?php } ?>
                                            
                                            <?php if (!empty($p['mp_matched_by'])) { ?>
                                            <div class="small">Matched by: <?php echo $p['mp_matched_by']; ?></div>
                                            <?php } ?>
                                            
                                            <hr>
                                            <?php if ($p['status'] == 1 && $p['product_type'] != 'variant' && $p['mp_sync_status_code'] != 'collision') { ?>
                                            Select <input type="checkbox" name="export-product[]" value="<?php echo $p['product_id_base']; ?>"><br>
                                            <form action="<?php echo $apply_mp_products_sync; ?>" method="post" style="display:inline;">
                                                <input type="hidden" name="oc_product_id" value="<?php echo (int)$p['product_id_base']; ?>" />
                                                
                                                <?php if ( !empty($p['mp_product']) && isset($p['mp_product']['id']) ) { ?>
                                                <input type="hidden" name="product_action" value="patch" />
                                                <a href="#" class="button link" onclick="this.closest('form').submit(); return false;">
                                                    <?php echo $button_mp_force_patch; ?>
                                                </a>
                                                <?php } else { ?>
                                                <input type="hidden" name="product_action" value="post" />
                                                <a href="#" class="button link" onclick="this.closest('form').submit(); return false;">
                                                    <?php echo $button_mp_force_post; ?>
                                                </a>
                                                <?php } ?>
                                                
                                            </form>
                                            <?php } else { ?>
                                            <span class="muted">export <?php echo $text_disabled; ?></span>
                                            <?php } ?>
                                            
                                        </div>
                                    </td>
                                    
                                    <td class="right">
                                        <div class="prices">
                                            <?php $hasSpecial = !empty($p['specials']); ?>
                                            <div class="base <?php echo $hasSpecial ? 'has-special' : ''; ?>">
                                                Base Price: <b><?php echo $this->currency->format($p['price'], $this->config->get('config_currency')) ; ?></b>
                                            </div>
                                            <?php if ($hasSpecial) { ?>
                                                <?php foreach ($p['specials'] as $sp) { ?>
                                                <div class="stack">Special Price: <b><?php echo $this->currency->format($sp['price'], $this->config->get('config_currency')) ; ?></b></div>
                                                <?php } ?>
                                            <?php } else { ?>
                                                <div class="muted">No special prices</div>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    
                                    <td class="left">
                                        <div class="options">
                                            <?php if (!empty($p['options_display'])) {
                                                if ($p['product_type'] === 'variable') {
                                                    // variable: show option names and values
                                                    foreach ($p['options_display'] as $op) {
                                                        echo '<div class="stack">'.$op['name'].':<br> <b>'.implode(',<br> ', $op['values']).'</b></div>';
                                                    }
                                                } else {
                                                    // variant: show the selected values
                                                    echo '<div class="stack">variant '.$p['options_display']['name'].':<br> <b>'.$p['options_display']['option'].'</b></div>';
                                                }
                                            } else {
                                                echo '<span class="muted">—</span>';
                                            } ?>
                                        </div>
                                    </td>
                                    
                                </tr>
                            <?php } } else { ?>
                                <tr>
                                    <td class="center" colspan="6"><?php echo $text_no_results; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="pagination"><?php echo $pagination; ?></div>
                
            </div>
            
            <div id="tab-categories" class="tab-content">
                <p><?php echo $text_categories_tab_info; ?></p>
                
                <table class="list">
                    <thead>
                        <tr>
                            <td class="left"><?php echo $col_category; ?> Opencart <hr> id :: <?php echo $col_name; ?></td>
                            <td class="left">Path <?php echo $col_categories; /* reuse as "OC Path" if you like */ ?></td>
                            <td class="left"><?php echo $col_category; ?> MerchantPro <hr> id :: <?php echo $col_name; ?></td>
                            <td class="left">Path <?php echo $col_mp_categories; ?></td>
                            <td class="left"><?php echo $col_mp_status; ?> <hr> <?php echo $col_action; ?></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($categories)) { foreach ($categories as $cat) { ?>
                            <tr>
                                <td>
                                    <small class="nowrap"><?php echo (int)$cat['category_id']; ?> :: <b><?php echo $cat['name']; ?></b></small>
                                    <!-- <?php echo (!empty($cat['image_url']) ? '<br><img src="'.$cat['image_url'].'" style="max-width: 100px; height: auto;">' : ''); ?> -->
                                </td>
                                <td>
                                    <?php echo !empty($cat['path']) ? '<small class="nowrap" style="font-size: .75em;">'.$cat['path'].'</small>' : '&dash;'; ?>
                                </td>
                                
                                <td>
                                    <?php echo (!empty($cat['mp_category_id']) ? '<small class="nowrap">'.$cat['mp_category_id'].' :: <b>'.$cat['mp_category_name'].'</b></small>' : '&dash;'); ?>
                                </td>
                                <td>
                                    <?php echo (!empty($cat['mp_category_path']) ? '<small class="nowrap" style="font-size: .75em;">'.$cat['mp_category_path'].'</small>' : '&dash;'); ?>
                                </td>
                                <td>
                                    
                                    <?php if (!empty($cat['mp_category_sync_status_code'])) { ?>
                                        <span class="mp-sync-badge mp-sync-<?php echo $cat['mp_category_sync_status_code']; ?>">
                                            <?php echo $cat['mp_category_sync_status_text']; ?>
                                        </span>
                                    <?php } ?>
                                    
                                    <?php if (in_array($cat['mp_category_sync_status_code'], array('ok','patch_name','patch_parent','patch_status','patch_complex')) && !empty($cat['mp_category_id'])) { ?>
                                        <form method="post" action="<?php echo $apply_mp_categories_sync; ?>" style="display:inline;">
                                            <input type="hidden" name="sync_action" value="patch" />
                                            <input type="hidden" name="oc_category_id" value="<?php echo (int)$cat['category_id']; ?>" />
                                            <input type="submit" value="<?php echo $button_mp_force_patch; ?>" class="button" style="border-radius: .5em; color: beige; background-color: #003A88; margin: .5em;" />
                                        </form>
                                    <?php } ?>
                                    <?php if ($cat['mp_category_sync_status_code'] == 'only_oc') { ?>
                                        <form method="post" action="<?php echo $apply_mp_categories_sync; ?>" style="display:inline;">
                                            <input type="hidden" name="sync_action" value="post" />
                                            <input type="hidden" name="oc_category_id" value="<?php echo (int)$cat['category_id']; ?>" />
                                            <input type="submit" value="<?php echo $button_mp_force_post; ?>" class="button" style="border-radius: .5em; color: beige; background-color: #003A88; margin: .5em;" />
                                        </form>
                                    <?php } ?>
                                    
                                </td>
                            </tr>
                        <?php } } else { ?>
                            <tr><td colspan="5"><?php echo $text_no_results; ?></td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            
            <div id="tab-mp-categories-delete" class="tab-content">
                <p><?php echo $text_mp_categories_delete_info; ?></p>
                
                <table class="list" style="width:100%;">
                    <thead>
                        <tr>
                            <td class="left" style="width:80px;"><?php echo $col_mp_category_id; ?></td>
                            <td class="left"><?php echo $col_mp_category_path; ?></td>
                            <td class="left"><?php echo $col_mp_status; ?></td>
                            <td class="left"><?php echo $col_action; ?></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($mpcategories_delete_only)) { foreach ($mpcategories_delete_only as $mpcat) { ?>
                            <tr>
                                <td><?php echo (int)$mpcat['mp_id']; ?></td>
                                <td><?php echo $mpcat['path']; ?></td>
                                <td>
                                    <span class="mp-sync-badge mp-cat-sync-only_mp">
                                        <?php echo $this->language->get('text_mp_cat_status_only_mp'); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" action="<?php echo $apply_mp_categories_sync; ?>" style="display:inline;">
                                        <input type="hidden" name="sync_action" value="delete" />
                                        <input type="hidden" name="mp_category_id" value="<?php echo (int)$mpcat['mp_id']; ?>" />
                                        <input type="submit" value="<?php echo $button_mp_force_delete; ?>" class="button" style="border-radius: .5em; color: beige; background-color: #003A88; margin: .5em;" />
                                    </form>
                                </td>
                            </tr>
                        <?php } } else { ?>
                            <tr><td colspan="4"><?php echo $text_no_results; ?></td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            
            <div id="tab-settings" class="tab-content">
                <form method="post" action="<?php echo $this->url->link('tool/sdx_export_to_mp_sync/APIsettings', 'token=' . $token, 'SSL'); ?>">
                    <input type="hidden" name="sdx_export_to_mp_sync_module" value="1" />
                    <table class="form">
                        <tr>
                            <td class="right"><?php echo $this->language->get('entry_api_url'); ?></td>
                            <td><input type="text" name="sdx_export_to_mp_sync_api[mp_api_url]" value="<?php echo isset($api['mp_api_url']) ? $api['mp_api_url'] : ''; ?>" style="width:320px;" /></td>
                        </tr>
                        <tr>
                            <td class="right"><?php echo $this->language->get('entry_api_name'); ?></td>
                            <td><input type="text" name="sdx_export_to_mp_sync_api[mp_api_name]" value="<?php echo isset($api['mp_api_name']) ? $api['mp_api_name'] : ''; ?>" style="width:320px;" /></td>
                        </tr>
                        <tr>
                            <td class="right"><?php echo $this->language->get('entry_api_key'); ?></td>
                            <td><input type="text" name="sdx_export_to_mp_sync_api[mp_api_key]" value="<?php echo isset($api['mp_api_key']) ? $api['mp_api_key'] : ''; ?>" style="width:320px;" /></td>
                        </tr>
                        <tr>
                            <td class="right"><?php echo $this->language->get('entry_api_secret'); ?></td>
                            <td><input type="text" name="sdx_export_to_mp_sync_api[mp_api_secret]" value="<?php echo isset($api['mp_api_secret']) ? $api['mp_api_secret'] : ''; ?>" style="width:320px;" /></td>
                        </tr>
                        <tr>
                            <td class="center" colspan="2">MerchantPro Feeds</td>
                        </tr>
                        <tr>
                            <td class="right"><?php echo $this->language->get('entry_feed_simple'); ?></td>
                            <td><input type="text" name="sdx_export_to_mp_sync_api[mp_feed_simple]" value="<?php echo isset($api['mp_feed_simple']) ? $api['mp_feed_simple'] : ''; ?>" style="width:320px;" /></td>
                        </tr>
                        <tr>
                            <td class="right"><?php echo $this->language->get('entry_feed_variants'); ?></td>
                            <td><input type="text" name="sdx_export_to_mp_sync_api[mp_feed_variants]" value="<?php echo isset($api['mp_feed_variants']) ? $api['mp_feed_variants'] : ''; ?>" style="width:320px;" /></td>
                        </tr>
                    </table>
                    <p><input type="submit" value="<?php echo $this->language->get('button_save'); ?>" class="button" style="border-radius: .5em; color: beige; background-color: #003A88; margin: .5em;" /></p>
                </form>
            </div>
        
        </div>
    </div>
</div>

<script type="text/javascript"><!--

$('#tabs a').tabs();

// Select/Deselect all active products
$('#select_all_products').on('change', function() {
    $('input[name="export-product[]"]').prop('checked', $(this).is(':checked'));
});

// Start export
$('#start-export').on('click', function() {
    
    if($('input[name="export-product[]"]:checked').length < 1 ) {
        //alert('No products selected for export! Total selections is ' + $('input[name="export-product[]"]:checked').length);
        alert('No products selected for export!');
        return;
    }
    
    var selectedIds = $('input[name="export-product[]"]:checked').map(function(){
        return $(this).val();
    }).get();
    
    //console.log(typeof selectedIds);
    //console.log('Selected IDs:', selectedIds, 'length:', selectedIds.length);
    
    // Save as JSON in hidden input
    $('#selected_json').val(JSON.stringify(selectedIds));

    // Show progress bar
    $('#export_progress').show();
    updateProgress(0);

    // For now: single AJAX request (later: batching)
    $.ajax({
        url: 'index.php?route=tool/sdx_export_to_mp_sync/exportProducts&token=<?php echo $token; ?>',
        type: 'post',
        dataType: 'json',
        data: { selected_json: $('#selected_json').val() },
        success: function(json) {
            if (json.success) {
                updateProgress(100);
                alert('Exported ' + json.export_count + ' product(s): ' + json.ids.join(', '));
            } else {
                alert('Error: ' + json.error);
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            alert('AJAX Error: ' + thrownError);
        }
    });
});

// Progress update helper
function updateProgress(percent) {
    $('#progress_bar').css('width', percent + '%').text(percent + '%');
}


(function($){
    $(document).ready(function(){
        // auto-submit filters (products tab)
        $('#product_status, #product_category').change(function(){
            var categories = $('#product_category option:selected').map(function(){ return $(this).val(); }).get();
            var status = $('#product_status').val();
            var url = 'index.php?route=tool/sdx_export_to_mp_sync&token=<?php echo $token; ?>';
            if (status !== '') url += '&product_status=' + encodeURIComponent(status);
            if (categories != '') url += '&product_category=' + encodeURIComponent(categories.join('-'));
            window.location = url;
        });

    });
})(jQuery);

//-->
</script>

<?php echo $footer; ?>
