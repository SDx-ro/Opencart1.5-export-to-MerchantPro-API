<?php

/* v1.5 controller SDxExportToMPSync */

class ControllerToolSdxExportToMpSync extends Controller {
    
    private $error = array();
    
    public function index() {
        $this->load->language('tool/sdx_export_to_mp_sync');
        $this->document->setTitle($this->language->get('heading_title'));
        
        $this->load->model('setting/setting');
        $this->load->model('tool/sdx_export_to_mp_sync');
        
        $url = '';
        
        // filters
        if (isset($this->request->get['product_status'])) {
            $product_status = $this->request->get['product_status'];
            $url .= '&product_status=' . $this->request->get['product_status'];
        } else {
            $product_status = '';
            //$product_status = 1;
        }
        
        if (isset($this->request->get['product_category'])) {
            $product_category = $this->request->get['product_category'];
            $url .= '&product_category=' . $this->request->get['product_category'];
        } else {
            $product_category = '';
        }
        
        if (isset($this->request->get['page'])) {
            $page = (int)$this->request->get['page'];
        } else {
            $page = 1;
        }
        
        // language & entries for template
        $this->data['heading_title']            = $this->language->get('heading_title');
        $this->data['tab_products']             = $this->language->get('tab_products');
        $this->data['tab_categories']           = $this->language->get('tab_categories');
        $this->data['tab_settings']             = $this->language->get('tab_settings');
        
        $this->data['text_success_saved']       = $this->language->get('text_success_saved');
        $this->data['text_export_placeholder']  = $this->language->get('text_export_placeholder');
        $this->data['text_filter_all']          = $this->language->get('text_filter_all');
        $this->data['text_filter_all_categories'] = $this->language->get('text_filter_all_categories');
        $this->data['text_no_results']          = $this->language->get('text_no_results');
        $this->data['text_home']                = $this->language->get('text_home');
        $this->data['text_enabled']             = $this->language->get('text_enabled');
        $this->data['text_disabled']            = $this->language->get('text_disabled');
        $this->data['text_selection']           = $this->language->get('text_selection');
        
        $this->data['text_current_mp_feed']     = $this->language->get('text_current_mp_feed');
        $this->data['text_no_mp_feed']          = $this->language->get('text_no_mp_feed');
        
        $this->data['text_mp_update_needed']      = $this->language->get('text_mp_update_needed');
        
        $this->data['button_export']            = $this->language->get('button_export');
        $this->data['button_filter']            = $this->language->get('button_filter');
        $this->data['button_update_mp_feed']    = $this->language->get('button_update_mp_feed');
        
        $this->data['entry_product_status']      = $this->language->get('entry_product_status');
        $this->data['entry_product_category']    = $this->language->get('entry_product_category');
        
        $this->data['col_product']              = $this->language->get('col_product');
        $this->data['col_category']             = $this->language->get('col_category');
        $this->data['col_name']                 = $this->language->get('col_name');
        $this->data['col_model']                = $this->language->get('col_model');
        $this->data['col_categories']           = $this->language->get('col_categories');
        $this->data['col_status']               = $this->language->get('col_status');
        $this->data['col_prices']               = $this->language->get('col_prices');
        $this->data['col_options']              = $this->language->get('col_options');
        
        $this->data['col_mp_status']            = $this->language->get('col_mp_status');
        
        $this->data['col_mp_categories']          = $this->language->get('col_mp_categories');
        $this->data['col_mp_sync_status']   = $this->language->get('col_mp_sync_status');
        
        // Category sync actions
        $this->data['button_mp_force_patch']    = $this->language->get('button_mp_force_patch');
        $this->data['button_mp_force_post']     = $this->language->get('button_mp_force_post');
        $this->data['button_mp_force_delete']   = $this->language->get('button_mp_force_delete');
        
        // Extra category sync tab
        $this->data['tab_mp_categories_delete']         = $this->language->get('tab_mp_categories_delete');
        $this->data['text_mp_categories_delete_info']   = $this->language->get('text_mp_categories_delete_info');
        
        // Optional columns (if you want more specific headers)
        $this->data['col_mp_category_id']   = $this->language->get('col_mp_category_id');
        $this->data['col_mp_category_path'] = $this->language->get('col_mp_category_path');
        $this->data['col_action']           = $this->language->get('col_action');
        
        $this->data['mp_status_in_mp']              = $this->language->get('mp_status_in_mp');
        $this->data['mp_status_in_mp_by_sku']       = $this->language->get('mp_status_in_mp_by_sku');
        $this->data['mp_status_out_of_sync']        = $this->language->get('mp_status_out_of_sync');
        $this->data['mp_status_price_stock_diff']   = $this->language->get('mp_status_price_stock_diff');
        $this->data['mp_status_collision']          = $this->language->get('mp_status_collision');
        $this->data['mp_status_missing']            = $this->language->get('mp_status_missing');
        $this->data['mp_status_no_feed']            = $this->language->get('mp_status_no_feed');
        
        $this->data['button_get_mp_taxonomies']     = $this->language->get('button_get_mp_taxonomies');
        
        $this->data['text_categories_tab_info'] = $this->language->get('text_categories_tab_info');
        
        // breadcrumbs
        $this->data['breadcrumbs'] = array();
        $this->data['breadcrumbs'][] = array(
            'text'      => $this->language->get('text_home'),
            'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => false
        );
        $this->data['breadcrumbs'][] = array(
            'text'      => $this->language->get('heading_title'),
            'href'      => $this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'] . $url, 'SSL'),
            'separator' => ' :: '
        );
        
        // tokens & filters
        $this->data['token'] = $this->session->data['token'];
        $this->data['product_status'] = $product_status;
        $this->data['product_category'] = $product_category;
        
        // load saved settings for Settings tab
        $settings = $this->model_setting_setting->getSetting('sdx_export_to_mp_sync');
        $api = isset($settings['sdx_export_to_mp_sync_api']) ? $settings['sdx_export_to_mp_sync_api'] : array();
        $module_flag = isset($settings['sdx_export_to_mp_sync_module']) ? $settings['sdx_export_to_mp_sync_module'] : '';
        // settings (for Settings tab)
        $this->data['api'] = $api;
        $this->data['module_flag'] = $module_flag;
        
        // build filter array
        $limit = $this->config->get('config_admin_limit');
        $filter = array(
            'product_status'   => $product_status,
            'product_category' => ($product_category !== '' ? explode('-', $product_category) : array()),
            'start'           => ($page - 1) * $limit,
            'limit'           => $limit
        );
        
        // totals & products
        // Totals (base products) and expanded products for current page
        $product_total = $this->model_tool_sdx_export_to_mp_sync->getTotalProducts($filter);
        // "original list" only for base products
        //$results = $this->model_tool_sdx_export_to_mp_sync->getProducts($filter);
        
        //$products = $this->model_tool_sdx_export_to_mp_sync->checkOCagainstMP($filter);
        $products = $this->model_tool_sdx_export_to_mp_sync->checkOCagainstMPapi($filter);
        if(!empty($products['error'])) { 
            if(isset($this->session->data['error'])){
                $this->session->data['error'] .= '<br>'.$products['error'];
            } else {
                $this->session->data['error'] = $products['error'];
            }
        }
        //if(!empty($products['success'])) { $this->session->data['success'] = 'all set!'; }
        $this->load->model('tool/image');
        $no_image = file_exists(DIR_IMAGE . 'no_image.jpg') ? HTTPS_CATALOG.'image/no_image.jpg' : (file_exists(DIR_IMAGE . 'no_image.png') ? HTTPS_CATALOG.'image/no_image.png' : '');
        $this->data['products'] = array();
        foreach ($products['oc'] as $product) {
            $thumb = (!empty($product['image']) && $product['product_type'] !== 'variant') ? $this->model_tool_image->resize($product['image'], 40, 40) : $no_image;
            $this->data['products'][] = array(
                'product_type'          => $product['product_type'],      // 'simple'|'variable'|'variant'
                'product_id'            => $product['product_id'],        // product_id (oc original for simple or variable, determined with option-slug for variant)
                'product_id_base'       => $product['product_id_base'],   // base product id (oc original, needed for variant)
                'ext_ref'               => $product['ext_ref'],           // determined ext_ref from oc (needed for matching/checking with mp products)
                'image'                 => $thumb,
                'view_product'          => $product['view_product'],
                'edit_product'          => $product['edit_product'],
                'name'                  => $product['name'],
                'model'                 => $product['model'],
                'model_base'            => $product['model_base'],
                'sku'                   => $product['sku'],
                'sku_base'              => $product['sku_base'],
                'mpn'                   => $product['mpn'],
                'mpn_base'              => $product['mpn_base'],
                'categories_paths'      => $product['categories_paths'],
                'categories_list'       => $product['categories_list'],
                'status'                => $product['status'],
                'quantity'              => $product['quantity'],
                'stock_status_id'       => $product['stock_status_id'],
                'stock_status_text'     => $product['stock_status_text'],
                'price'                 => $product['price'],
                'specials'              => $product['specials'],
                'options_display'       => $product['options_display'],
                'mp_id'                 => $product['mp_id'],
                'mp_sync_status_code'   => $product['mp_sync_status_code'],
                'mp_sync_status'        => $product['mp_sync_status'],
                'mp_sync_issues'        => $product['mp_sync_issues'],
                'mp_matched_by'         => $product['mp_matched_by']
            );
        }
        
        $current_date = new DateTime(); // Current date and time
        $current_date = $current_date->format('Y-m-d H:i:s'); // formated current date and time
        
        if (!empty($products['file']) && file_exists($products['file'])) {
                    $mpprods_date = date('Y-m-d H:i:s', strtotime('+3 hours', filemtime($products['file']))); // mp products api cache date and time
                    $this->data['mpprodsdate'] = ' at <b>'.$mpprods_date.' (+3h)</b> vs. now <b>'.$current_date.'</b>';
                    if ($mpprods_date < $current_date) {
                        $this->data['updatempprods'] = true;
                    } else {
                        $this->data['updatempprods'] = false;
                    }
                    $this->data['mpproducts_file'] = $products['file'];
                    $this->data['mpproducts_source'] = 'Merchantpro Products from Local Cache';
        } else { 
            $this->data['updatempprods'] = true; 
            $this->data['mpprodsdate'] = 'no date for MP API products'; 
            $this->data['mpproducts_file'] = 'MerchantPro Products API/cache failed';
            $this->data['mpproducts_source'] = 'none -> MP Products API/cache failed';
        }
        
        // URL (for form to post) for the apply MP API endpoint for products (used by all per-product buttons or batch action)
        $this->data['apply_mp_products_sync'] = $this->url->link('tool/sdx_export_to_mp_sync/applyMpProductsSync', 'token=' . $this->session->data['token'], 'SSL');
        
        // get latest MP xlsx files
        $xlsxfilepaths = $this->model_tool_sdx_export_to_mp_sync->getLatestXLSXFeedFiles();
        if (!empty($xlsxfilepaths)) {
            $this->data['mp_export_consolidated_filepath'] = strpos($xlsxfilepaths[0], '_feed-all-products_') !== false ? $xlsxfilepaths[0] : '';
            $this->data['mp_export_consolidated_file'] = strpos($xlsxfilepaths[0], '_feed-all-products_') !== false ? basename($xlsxfilepaths[0]) : '';
            $this->data['mp_export_simple_file'] = isset($xlsxfilepaths[1]) ? (strpos($xlsxfilepaths[1], '_feed-simple_') !== false ? basename($xlsxfilepaths[1]) : '') : '';
            $this->data['mp_export_variants_file'] = isset($xlsxfilepaths[2]) ? (strpos($xlsxfilepaths[2], '_feed-variants_') !== false ? basename($xlsxfilepaths[2]) : '') : '';
        } else {
            $this->data['mp_export_consolidated_filepath'] = '';
            $this->data['mp_export_consolidated_file'] = '';
            $this->data['mp_export_simple_file'] = '';
            $this->data['mp_export_variants_file'] = '';
        }
        // check the consolidated feed date and time vs. current date and time
        if (file_exists($this->data['mp_export_consolidated_filepath'])) {
            $feed_date = date('Y-m-d H:i:s', strtotime('+3 hours', filemtime($this->data['mp_export_consolidated_filepath']))); // formated consolidated feed's date and time
            $this->data['feeddate'] = 'at <b>'.$feed_date.' (+3h)</b> vs. now <b>'.$current_date.'</b>';
            if ($feed_date < $current_date) {
                $this->data['updatefeeds'] = true;
            } else {
                $this->data['updatefeeds'] = false;
            }
        } else {
            $this->data['updatefeeds'] = true;
            $this->data['feeddate'] = 'no feed date';
        }
        
        // categories (for selector and categories tab) + MP sync status
        $sync = $this->computeCategorySyncStatus();
        
        // Used for product filter selector and categories tab
        $this->data['categories']                   = $sync['oc_with_status']; // oc categories
        $this->data['mpcategories_delete_only']     = $sync['mp_only'];
        $this->data['mpcategories_total']           = $sync['mp_total'];
        $this->data['mpcategories_cache']           = !empty($sync['mp_cache']) ? $sync['mp_cache'] : 'none -> error!'; // full path of local cache file for MP categories (empty or not - just the file or none -> error)
        $this->data['mpcategories_source']          = !empty($sync['mp_source']) ? $sync['mp_source'] : 'none -> MP Categories API/cache failed'; // info txt about MP Categories source (API, Cache or none -> fail/error)
        $this->data['mpcategories_file']            = !empty($sync['mp_file']) ? $sync['mp_file'] : (!empty($sync['mp_source']) ? 'MerchantPro Categories API' : 'MerchantPro Categories API/cache failed'); // full path of local cache file for MP categories, only if cache file was used
        
        $this->data['mpcategories_patch_items']     = $sync['patch_items'];
        $this->data['mpcategories_post_items']      = $sync['post_items'];
        $this->data['mpcategories_delete_items']    = $sync['delete_items'];
        $this->data['mpcategories_json_files']      = $sync['mp_json_files'];
        
        if (!empty($sync['mp_source']) && !empty($sync['mp_cache']) && file_exists($sync['mp_cache'])) {
                    $mpcats_date = date('Y-m-d H:i:s', strtotime('+3 hours', filemtime($sync['mp_cache']))); // formated consolidated feed's date and time
                    $this->data['mpcatsdate'] = ' at <b>'.$mpcats_date.' (+3h)</b> vs. now <b>'.$current_date.'</b>';
                    if ($mpcats_date < $current_date) {
                        $this->data['updatempcats'] = true;
                    } else {
                        $this->data['updatempcats'] = false;
                    }
        } else { $this->data['updatempcats'] = true; $this->data['mpcatsdate'] = 'no date for MP API categories'; }
        
        // URL (for form to post) for the apply MP API endpoint for categories (used by all per-category buttons or batch action)
        $this->data['apply_mp_categories_sync'] = $this->url->link('tool/sdx_export_to_mp_sync/applyMpCategoriesSync', 'token=' . $this->session->data['token'], 'SSL');
        
        // check for MP Taxes taxonomy
        $mptaxes = $this->model_tool_sdx_export_to_mp_sync->getMpTvaFromCache();
        if (!empty($mptaxes['file']) && file_exists($mptaxes['file'])) {
                    $mptax_date = date('Y-m-d H:i:s', strtotime('+3 hours', filemtime($mptaxes['file']))); // formated consolidated feed's date and time
                    $this->data['mptaxdate'] = ' at <b>'.$mptax_date.' (+3h)</b> vs. now <b>'.$current_date.'</b>';
                    if ($mptax_date < $current_date) {
                        $this->data['updatemptaxes'] = true;
                    } else {
                        $this->data['updatemptaxes'] = false;
                    }
                    $this->data['mptaxes_file'] = $mptaxes['file'];
                    $this->data['mptaxes_source'] = 'Merchantpro Taxonomies from Local Cache';
        } else { 
            $this->data['updatemptaxes'] = true; 
            $this->data['mptaxdate'] = 'no date for MP API taxonomies';
            $this->data['mptaxes_file'] = 'MerchantPro Taxonomies API/cache failed';
            $this->data['mptaxes_source'] = 'none -> MP Taxonomies API/cache failed';
        }
        
        // export url (placeholder)
        $this->data['export'] = $this->url->link('tool/sdx_export_to_mp_sync/export', 'token=' . $this->session->data['token'], 'SSL');
        
        // messages
        //$this->data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        if (isset($this->session->data['error'])) {
            $this->data['error_warning'] = $this->session->data['error'];
            unset($this->session->data['error']);
            isset($this->error['warning']) ? $this->data['error_warning'] .= '<br>'.$this->error['warning'] : '';
        } else {
            //$this->data['error_warning'] = '';
            $this->data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        }
        
        if (isset($this->session->data['success'])) {
            $this->data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $this->data['success'] = '';
        }
        
        // pagination
        $pagination = new Pagination();
        $pagination->total = $product_total;
        $pagination->page  = $page;
        $pagination->limit = $limit;
        $pagination->text  = $this->language->get('text_pagination');
        $pagination->url   = $this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'] . $url . '&page={page}', 'SSL');
        
        $this->data['pagination'] = $pagination->render();
        
        $this->template = 'tool/sdx_export_to_mp_sync.tpl';
        $this->children = array('common/header', 'common/footer');
        
        $this->response->setOutput($this->render());
    }
    
    // download method for xlsx files (consolidated feed, simple+variable feed, variant feed)
    public function mpXLSXdownload() {
        $this->load->language('tool/sdx_export_to_mp_sync');
        
        if (!isset($this->request->get['file'])) {
            $this->session->data['error'] = $this->language->get('error_no_file_specified');
            $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
        }
        
        $file = basename($this->request->get['file']); // sanitize input, only filename allowed
        $allowed_prefixes = array(
            '_feed_simple_',
            '_feed_variants_',
            '_mp-export_'
        );
        
        $is_allowed = false;
        foreach ($allowed_prefixes as $prefix) {
            if (strpos($file, $prefix) !== false) {
                $is_allowed = true;
                break;
            }
        }
        
        if (!$is_allowed) {
            $this->session->data['error'] = $this->language->get('error_invalid_file');
            $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
        }
        
        $filepath = DIR_LOGS . $file;
        
        if (!is_file($filepath)) {
            $this->session->data['error'] = $this->language->get('error_file_not_found');
            $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
        }
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($filepath));
        ob_clean();
        flush();
        readfile($filepath);
        exit;
    }
    
    // triggers the model's updateMPfeed() method. * Calls the model method and sets session success/error then redirects back.
    public function updateConsolidatedMPfeed() {
        
        $this->load->language('tool/sdx_export_to_mp_sync');
        $this->load->model('tool/sdx_export_to_mp_sync');
        
        // Permission check
        if (!$this->user->hasPermission('modify', 'tool/sdx_export_to_mp_sync')) {
            $this->session->data['error'] = $this->language->get('error_permission');
            $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
            return;
        }
        
        // Call the model method updateMPfeed()
        try {
            $result = $this->model_tool_sdx_export_to_mp_sync->updateMPfeed();
        } catch (Exception $e) {
            $this->session->data['error'] = $this->language->get('error_mp_feed_update') . ' - ' . $e->getMessage();
            $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
            return;
        }
        
        if (is_array($result)) {
            if (!empty($result['success'])) {
                $this->session->data['success'] = $this->language->get('text_mp_feed_updated') . (isset($result['filename']) ? ' ' . $result['filename'] : '');
            } else {
                $this->session->data['error'] = isset($result['error']) ? $result['error'] : $this->language->get('error_mp_feed_update');
            }
        } else {
            $this->session->data['error'] = $this->language->get('error_mp_feed_update');
        }
        
        // Redirect back to the tool page
        $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
    }
    
    public function APIsettings() {
        //..
        $this->load->language('tool/sdx_export_to_mp_sync');
        $this->load->model('setting/setting');
        
        // Save / Delete settings
        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            if (!isset($this->request->post['sdx_export_to_mp_sync_module'])) {
                // delete settings when module key not included
                if ($this->validate()) {
                    $this->model_setting_setting->editSetting('sdx_export_to_mp_sync', array());
                    $this->session->data['success'] = $this->language->get('text_success_saved');
                    $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
                }
            } else {
                // save settings (module presence + api array)
                if ($this->validate()) {
                    $save = array(
                        'sdx_export_to_mp_sync_module' => $this->request->post['sdx_export_to_mp_sync_module'],
                        'sdx_export_to_mp_sync_api'    => isset($this->request->post['sdx_export_to_mp_sync_api']) ? $this->request->post['sdx_export_to_mp_sync_api'] : array()
                    );
                    $this->model_setting_setting->editSetting('sdx_export_to_mp_sync', $save);
                    $this->session->data['success'] = $this->language->get('text_success_saved');
                    $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
                }
            }
        }
    }
    // validate for permisions and important MP API settings/credentials
    private function validate() {
        
        if (!$this->user->hasPermission('modify', 'tool/sdx_export_to_mp_sync')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        
        // If saving API settings (POST has array), require both fields
        if (isset($this->request->post['sdx_export_to_mp_sync_api'])) {
            $api = $this->request->post['sdx_export_to_mp_sync_api'];
            if (empty($api['mp_api_url']) || empty($api['mp_api_key']) || empty($api['mp_api_secret'])) {
                $this->error['warning'] = $this->language->get('error_api_required');
            }
        }
        
        return !$this->error;
    }
    
    // === MP API helpers ===
    // get the MP API settings/credentials
    private function mpApiSettings() {
        // Retrieve saved API settings (array with keys mp_api_url, mp_api_key, mp_api_secret)
        $settings = $this->model_setting_setting->getSetting('sdx_export_to_mp_sync');
        $api = isset($settings['sdx_export_to_mp_sync_api']) ? $settings['sdx_export_to_mp_sync_api'] : array();
        // allow config fallback
        if (!$api && $this->config->get('sdx_export_to_mp_sync_api')) {
            $api = $this->config->get('sdx_export_to_mp_sync_api');
        }
        $base = isset($api['mp_api_url']) ? rtrim($api['mp_api_url'], '/') : '';
        $key  = isset($api['mp_api_key']) ? $api['mp_api_key'] : '';
        $sec  = isset($api['mp_api_secret']) ? $api['mp_api_secret'] : '';
        $store_slug = $this->model_tool_sdx_export_to_mp_sync->deriveStoreSlugFromUrl($base);
        $valid = ($base !== '' && $key !== '' && $sec !== '');
        if (!$valid && empty($this->session->data['error'])) {
            $this->session->data['error'] = $this->language->get('error_api_required');
            //$this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
            //$this->redirect($this->url->link('tool/sdx_export_to_mp_sync/apierror', 'token=' . $this->session->data['token'], 'SSL'));
            //exit;
        }
        return array('base'=>$base, 'key'=>$key, 'secret'=>$sec, 'store_slug' => $store_slug, 'valid' => $valid);
    }
    
    // the MP API request
    private function mpRequest($method, $pathOrUrl, $query = array(), $body = null, $maxRetries = 4) {
        $set = $this->mpApiSettings();

        if (empty($set['valid'])) {
            if (empty($this->session->data['error'])) {
                $this->session->data['error'] = $this->language->get('error_api_required');
            }
            return array(
                'status'  => 0,
                'headers' => array(),
                'json'    => null,
                'error'   => $this->language->get('error_api_required')
            );
        }
        
        // Build URL
        $url = (strpos($pathOrUrl, 'http') === 0) ? $pathOrUrl : $set['base'] . $pathOrUrl;
        if (!empty($query)) {
            $qs = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            $url .= (strpos($url, '?') === false ? '?' : '&') . $qs;
        }
        
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($set['key'] . ':' . $set['secret'])
        );
        
        $retry     = 0;
        $sleep     = 1.0;          // seconds, exponential backoff
        $lastError = null;
        
        // Simple local throttle (~3–4 req/s; MP limit is 4/sec)
        static $lastCallTs = 0.0;
        
        do {
            // --- local rate limiting ---
            $now = microtime(true);
            if ($lastCallTs > 0) {
                $minInterval = 0.30; // seconds between calls
                $delta       = $now - $lastCallTs;
                if ($delta < $minInterval) {
                    usleep((int)(($minInterval - $delta) * 1000000));
                }
            }
            $lastCallTs = microtime(true);
            
            $respHeaders = array();
            
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => false,
                CURLOPT_CUSTOMREQUEST  => strtoupper($method),
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => false, // DO NOT auto-follow 30x
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HEADERFUNCTION => function($ch, $h) use (&$respHeaders) {
                    $len   = strlen($h);
                    $parts = explode(':', $h, 2);
                    if (count($parts) == 2) {
                        $name  = strtolower(trim($parts[0]));
                        $value = trim($parts[1]);
                        if (!isset($respHeaders[$name])) {
                            $respHeaders[$name] = array();
                        }
                        $respHeaders[$name][] = $value;
                    }
                    return $len;
                },
            ));
            
            if ($body !== null) {
                curl_setopt(
                    $ch,
                    CURLOPT_POSTFIELDS,
                    (is_array($body) || is_object($body))
                        ? json_encode($body, JSON_UNESCAPED_UNICODE)
                        : (string)$body
                );
            }
            
            $raw    = curl_exec($ch);
            $errno  = curl_errno($ch);
            $errstr = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Decode JSON if any
            $json = null;
            if ($raw !== '' && $raw !== null) {
                $dec = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $json = $dec;
                }
            }
            
            // --- Network / cURL error ---
            if ($errno) {
                $lastError = 'cURL error #' . $errno . ($errstr ? ': ' . $errstr : '');
                $retry++;
                if ($retry >= $maxRetries) {
                    return array(
                        'status'  => 0,
                        'headers' => $respHeaders,
                        'json'    => null,
                        'error'   => $lastError
                    );
                }
                usleep((int)($sleep * 1000000));
                $sleep = min($sleep * 2, 10.0);
                continue;
            }
            
            // --- 429 Too Many Requests (rate limit exceeded) ---
            if ($status === 429) {
                $lastError = 'HTTP 429 Too Many Requests while calling ' . $url;
                $retry++;
                if ($retry >= $maxRetries) {
                    return array(
                        'status'  => $status,
                        'headers' => $respHeaders,
                        'json'    => $json,
                        'error'   => $lastError
                    );
                }
                
                $retryAfter = 0;
                if (isset($respHeaders['retry-after'][0])) {
                    $retryAfter = (int)$respHeaders['retry-after'][0];
                }
                
                if ($retryAfter > 0 && $retryAfter < 3600) {
                    sleep($retryAfter);
                } else {
                    usleep((int)($sleep * 1000000));
                    $sleep = min($sleep * 2, 10.0);
                }
                continue;
            }
            
            // --- 2xx → success (200/201/204 etc.) ---
            if ($status >= 200 && $status < 300) {
                return array(
                    'status'  => $status,
                    'headers' => $respHeaders,
                    'json'    => $json,
                    'error'   => null
                );
            }
            
            // --- 3xx → redirect (likely wrong API base URL) ---
            if ($status >= 300 && $status < 400) {
                $location = isset($respHeaders['location'][0]) ? $respHeaders['location'][0] : '';
                $err      = 'Unexpected HTTP ' . $status . ' redirect when calling ' . $url;
                if ($location) {
                    $err .= '<br> (Location: ' . $location . ')';
                }
                $err     .= '<br> Check MerchantPro API base URL.';
                return array(
                    'status'  => $status,
                    'headers' => $respHeaders,
                    'json'    => $json,
                    'error'   => $err
                );
            }
            
            // --- 4xx → client errors (400, 401, 403, 404, 405, etc.) ---
            if ($status >= 400 && $status < 500) {
                $err = 'HTTP ' . $status . ' error when calling ' . $url;
                
                if (is_array($json) && isset($json['error'])) {
                    $name = isset($json['error']['name']) ? $json['error']['name'] : '';
                    $msg  = isset($json['error']['message']) ? $json['error']['message'] : '';
                    if ($name || $msg) {
                        $err .= '<br> -> ';
                        if ($name) {
                            $err .= $name;
                        }
                        if ($msg) {
                            if ($name) {
                                $err .= ' - ';
                            }
                            $err .= $msg;
                        }
                    }
                }
                
                return array(
                    'status'  => $status,
                    'headers' => $respHeaders,
                    'json'    => $json,
                    'error'   => $err
                );
            }
            
            // --- 5xx → server errors, retry a few times ---
            if ($status >= 500) {
                $lastError = 'HTTP ' . $status . ' server error when calling ' . $url;
                $retry++;
                if ($retry >= $maxRetries) {
                    return array(
                        'status'  => $status,
                        'headers' => $respHeaders,
                        'json'    => $json,
                        'error'   => $lastError
                    );
                }
                usleep((int)($sleep * 1000000));
                $sleep = min($sleep * 2, 10.0);
                continue;
            }
            
            // --- any other weird status ---
            $lastError = 'Unexpected HTTP status ' . $status . ' when calling ' . $url;
            return array(
                'status'  => $status,
                'headers' => $respHeaders,
                'json'    => $json,
                'error'   => $lastError
            );
            
        } while ($retry < $maxRetries);
        
        // Safety net (should not normally reach here)
        return array(
            'status'  => 0,
            'headers' => array(),
            'json'    => null,
            'error'   => $lastError ? $lastError : 'Unknown error while calling MerchantPro API: ' . $url
        );
    }
    
    // to be called when pagination is needed, calls mpRequest 
    private function mpFetchAll($path, $query, $dataKey = 'data') {
        $allRows     = array();
        $pages       = 0;
        $lastResp    = null;
        
        // First page
        $r = $this->mpRequest('GET', $path, $query);
        $lastResp = $r;
        
        if ($r['status'] !== 200 || !is_array($r['json']) || !isset($r['json'][$dataKey]) || !is_array($r['json'][$dataKey])) {
            $msg = !empty($r['error'])
                    ? $r['error']
                    : 'MerchantPro API unexpected response (HTTP ' . $r['status'] . ') while fetching ' . $path;
            //throw new Exception($msg);
            return array(
                'status'  => $r['status'],
                'headers' => $r['headers'],
                'json'    => $r['json'],
                'error'   => $msg
            );
        }
        
        $rows = $r['json'][$dataKey];
        if ($rows) {
            $allRows = array_merge($allRows, $rows);
        }
        $next  = isset($r['json']['meta']['links']['next']) ? $r['json']['meta']['links']['next'] : null;
        $pages = 1;
        
        // Follow pagination
        while ($next) {
            $r = $this->mpRequest('GET', $next);
            $lastResp = $r;
            
            if ($r['status'] !== 200 || !is_array($r['json']) || !isset($r['json'][$dataKey]) || !is_array($r['json'][$dataKey])) {
                $msg = !empty($r['error'])
                    ? $r['error']
                    : 'MerchantPro API unexpected response (HTTP ' . $r['status'] . ') while fetching ' . $next;
                //throw new Exception($msg);
                return array(
                    'status'  => $r['status'],
                    'headers' => $r['headers'],
                    'json'    => $r['json'],
                    'error'   => $msg
                );
            }
            
            $rows = $r['json'][$dataKey];
            if ($rows) {
                $allRows = array_merge($allRows, $rows);
            }
            $next = isset($r['json']['meta']['links']['next']) ? $r['json']['meta']['links']['next'] : null;
            $pages++;
        }
        
        // Build synthetic mpRequest-like response with aggregated data
        $json = $lastResp['json'];
        $json[$dataKey] = $allRows;
        if (!isset($json['meta']) || !is_array($json['meta'])) {
            $json['meta'] = array();
        }
        $json['meta']['fetched_pages'] = $pages;
        $json['meta']['items']         = count($allRows);
        
        return array(
            'status'  => $lastResp['status'],
            'headers' => $lastResp['headers'],
            'json'    => $json,
            'error'   => !empty($lastResp['error']) ? $lastResp['error'] : ''
        );
    }
    
    // === MP API: get ALL products (with pagination) + optional JSON cache ===
    private function mpGetAllProducts($writeFile = true) {
        $set = $this->mpApiSettings();

        if (empty($set['valid'])) {
            return array(
                'success'      => false,
                'error'        => $this->language->get('error_api_required'),
                'mp_prodcache' => null,
                'mp_products'  => array(),
                'meta'         => array(),
                'source'       => ''
            );
        }

        // Cache path -> find latest json cache file (pattern)
        //$cachepattern = DIR_LOGS . $set['store_slug'] . '_mp-export_all-products-cache_*.json';
        $cachepattern = DIR_LOGS . $set['store_slug'] . '_mp-export_api-products-cache_*.json';
        $cachefiles   = glob($cachepattern);
        
        $cachefile = null;
        
        if ($cachefiles) {
            // newest first
            usort($cachefiles, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            $cachefile = $cachefiles[0];
        } else {
            //$cachefile = DIR_LOGS . $set['store_slug'] . '_mp-export_all-products-cache_' . date('Y-m-d') . '.json';
            $cachefile = DIR_LOGS . $set['store_slug'] . '_mp-export_api-products-cache_' . date('Y-m-d') . '.json';
        }
        
        // If we are NOT forcing refresh and have an existing cache file, try to use it
        if (is_file($cachefile) && !$writeFile) {
            $cachedata = file_get_contents($cachefile);
            $rawdata   = json_decode($cachedata, true);
            
            if (is_array($rawdata) && isset($rawdata['json']) && isset($rawdata['json']['data'])) {
                // Old XLSX-based file may have different structure – if so, just ignore and rebuild.
                // Here we assume it's already in the mpFetchAll format we are writing below.
                return array(
                    'success'      => true,
                    'source'       => $cachefile,
                    'meta'         => isset($rawdata['json']['meta']) ? $rawdata['json']['meta'] : array(),
                    'total'        => isset($rawdata['json']['meta']['items']) ? (int)$rawdata['json']['meta']['items'] : count($rawdata['json']['data']),
                    'mp_products'  => $rawdata['json']['data'],
                    //'mp_prodcache' => true,
                    'mp_prodcache' => $cachefile,
                    'error'         => null
                );
            }
            // If structure is not what we expect -> fall-through and rebuild from API
        }
        
        // --- Build from API using mpFetchAll ---
        $resp = array();
        $resp['generated_at'] = date('c');
        
        try {
            // You can refine fields later; for now we let API return the usual product summary
            // and side-load variants (if needed) in one go.
            $records = $this->mpFetchAll('/api/v2/products', array(
                'limit'   => 100,              // MP max = 100
                'include' => 'variants',       // add ',images' if you want images
                // 'status'  => 'active',      // uncomment if you want active only
                'fields'  => 'id,type,sku,ext_ref,name,stock,price_net,price_gross,old_price_gross,old_price_net,inventory_enabled,status,category_id,category_name,categories'
            ));
            
            if(!isset($records['json']) || !empty($records['error'])) {
                return array(
                    'success' => false,
                    'source' => 'none',
                    'meta' => 'none',
                    'total' => 0,
                    'mp_products' => array(),
                    'mp_prodcache' => $cachefile,
                    'error' => 'API error for MP Products <br> -> '.$records['error']
                );
            }
            
            // Optionally normalize some numeric fields
            //if (isset($records['json']['data']) && is_array($records['json']['data'])) {
            //    foreach ($records['json']['data'] as &$r) {
            //        if (isset($r['id']))        $r['id']        = (int)$r['id'];
            //        if (isset($r['category_id'])) $r['category_id'] = (int)$r['category_id'];
            //        if (isset($r['tax_id']))    $r['tax_id']    = (int)$r['tax_id'];
            //        if (isset($r['stock']))     $r['stock']     = (float)$r['stock'];
            //    }
            //    unset($r);
            //}
            
            $resp = array_merge($resp, $records);
            
            //if ($writeFile) {
                // delete any existing api-products-cache json files for this store_slug
                if ($cachefiles) { foreach ($cachefiles as $df) { @unlink($df); } }
                file_put_contents($cachefile, json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            //}
            
            return array(
                'success'      => true,
                'source'       => isset($resp['json']['meta']['links']['current'])
                                    ? $resp['json']['meta']['links']['current']
                                    : $cachefile,
                'meta'         => isset($resp['json']['meta']) ? $resp['json']['meta'] : array(),
                'total'        => isset($resp['json']['meta']['items']) ? (int)$resp['json']['meta']['items']
                                                                         : (isset($resp['json']['data']) ? count($resp['json']['data']) : 0),
                'mp_products'  => isset($resp['json']['data']) ? $resp['json']['data'] : array(),
                //'mp_prodcache' => false,
                'mp_prodcache' => $cachefile,
                'error' => null
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'source' => 'none',
                'meta' => 'none',
                'total' => 0,
                'mp_products' => array(),
                'mp_prodcache' => $cachefile,
                'error' => 'API error for MP Products <br> -> '.$e->getMessage()
            );
        }
    }
    
    public function buildMPallProductsCache() {
        $this->load->language('tool/sdx_export_to_mp_sync');
        $this->load->model('setting/setting');
        $this->load->model('tool/sdx_export_to_mp_sync');
        
        $set = $this->mpApiSettings();
        
        if (!$this->user->hasPermission('modify', 'tool/sdx_export_to_mp_sync')) {
            $this->session->data['error'] = $this->language->get('error_permission');
            $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
        }
        
        try {
            
            $prods = $this->mpGetAllProducts(); // use false to read the cache or true/empty to write cache
            
            if (!empty($prods['success'])) {
                $total = (int)$prods['total'];
                
                // Reuse your existing text_json_prepared if you like, or just hardcode text
                if ($this->language->get('text_json_prepared')) {
                    $this->session->data['success'] = sprintf(
                        $this->language->get('text_json_prepared'),
                        'The MP API call for Products returned',
                        $total,
                        'in DIR_LOGS: ' . $set['store_slug'] . '_mp-export_api-products-cache_*.json'
                    );
                } else {
                    $this->session->data['success'] =
                        'The MP API call for Products returned ' . $total .
                        ' rows. Cache file: ' . $set['store_slug'] . '_mp-export_api-products-cache_*.json';
                }
                
            } else {
                $msg = !empty($prods['error']) ? $prods['error'] : 'Unknown error while building MP products cache.';
                $this->session->data['error'] = 'MP Products cache error: ' . $msg;
            }
        
        } catch (Exception $e) {
            $this->session->data['error'] = 'The MP API call for Products failed: ' . $e->getMessage();
        }
        
        $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
    }
    
    // get and create MP categories cache
    private function mpGetAllCategories($writeFile = true) {
        $set = $this->mpApiSettings();
        
        // cache path -> find latest json cache file (pattern)
        $cachepattern = DIR_LOGS . $set['store_slug'] . '_mp-export_all-categories-cache_*.json';
        $cachefiles = glob($cachepattern);
        if ($cachefiles) {
            usort($cachefiles, function($cfa, $cfb) { return filemtime($cfb) - filemtime($cfa); });
            $cachefile = $cachefiles[0];
        } else { $cachefile = DIR_LOGS . $set['store_slug'] . '_mp-export_all-categories-cache_' . date('Y-m-d') . '.json'; }
        
        // if cache exists and fresh -> return it, else force rebuild
        if (is_file($cachefile) && !$writeFile ) {
            $cachedata = file_get_contents($cachefile);
            $rawdata = json_decode($cachedata, true);
            if (is_array($rawdata) && isset($rawdata['json'])) {
                return array(
                    'success' => true,
                    'source' => $cachefile,
                    'meta' => $rawdata['json']['meta'],
                    'total' => $rawdata['json']['meta']['items'],
                    'mp_categories' => $rawdata['json']['data'],
                    'mp_catcache' => $cachefile,
                    'error' => null
                );
            }
        }
        
        $resp = array();
        $resp['generated_at'] = date('c');
        
        try {
            $records = $this->mpFetchAll('/api/v2/categories', array(
                'limit'  => 100,
                'fields' => 'id,parent_id,name,description,meta_title,meta_description,meta_noindex,page_template,url,meta_fields,subcategories,default_sorting,custom_order,status'
                //'status' => 'active'
            ));
            
            if(!isset($records['json']) || !empty($records['error'])) {
                return array(
                    'success' => false,
                    'source' => 'none',
                    'meta' => 'none',
                    'total' => 0,
                    'mp_categories' => array(),
                    'mp_catcache' => $cachefile,
                    'error' => 'API error for MP Categories <br> -> '.$records['error']
                );
            }
            
            // Optionally normalize some numeric fields
            foreach ($records['json']['data'] as &$r) {
                if (isset($r['id'])) $r['id'] = (int)$r['id'];
                if (isset($r['parent_id'])) $r['parent_id'] = (int)$r['parent_id'];
            }
            unset($r);
            
            $resp = array_merge($resp, $records);
            
            //if ($writeFile) {
                // delete any existing all-categories-cache json file for this store_slug
                if ($cachefiles) { foreach ($cachefiles as $df) { @unlink($df); } }
                file_put_contents($cachefile, json_encode($resp, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
            //}
            
            return array(
                'success' => true, 
                'source' => $resp['json']['meta']['links']['current'], 
                'meta' => $resp['json']['meta'], 
                'total' => $resp['json']['meta']['items'], 
                'mp_categories' => $resp['json']['data'],
                'mp_catcache' => $cachefile,
                'error' => null
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'source' => 'none',
                'meta' => 'none',
                'total' => 0,
                'mp_categories' => array(),
                'mp_catcache' => $cachefile,
                'error' => 'API error for MP Categories <br> -> '.$e->getMessage()
            );
        }
    }
    
    // to be called to get/create MP categories cache 
    public function buildMPallCategoriesCache() {
        $this->load->language('tool/sdx_export_to_mp_sync');
        $this->load->model('setting/setting');
        $this->load->model('tool/sdx_export_to_mp_sync');
        $set = $this->mpApiSettings();
        if (!$this->user->hasPermission('modify', 'tool/sdx_export_to_mp_sync')) {
            $this->session->data['error'] = $this->language->get('error_permission');
            $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
        }
        // ---- Fetch MP categories (from cache or API) ----
        $mpcategories = $this->mpGetAllCategories(); // use false to read the cache or true/empty to write cache
        if (empty($mpcategories['success'])) {
            //throw new Exception('Could not fetch MerchantPro categories.');
            if(isset($this->session->data['error'])){
                $this->session->data['error'] .= '<br> Could not fetch MerchantPro categories via API or cache file.'.(isset($mpcategories['error']) ? '<br>'.$mpcategories['error'] : '');
            } else {
                $this->session->data['error'] = '<br> Could not fetch MerchantPro categories via API or cache file.'.(isset($mpcategories['error']) ? '<br>'.$mpcategories['error'] : '');
            }
        } else {
            if(isset($this->session->data['success'])){
                $this->session->data['success'] .= '<hr>'.sprintf(
                    $this->language->get('text_json_prepared'), 
                    'The MP API call for Categories returned', 
                    count($mpcategories['mp_categories']), 
                    'in DIR_LOGS: ' . $set['store_slug'].'_mp-export_all-categories-cache_*.json '
                );
            } else{
                $this->session->data['success'] = sprintf(
                    $this->language->get('text_json_prepared'), 
                    'The MP API call for Categories returned', 
                    count($mpcategories['mp_categories']), 
                    'in DIR_LOGS: ' . $set['store_slug'].'_mp-export_all-categories-cache_*.json '
                );
            }
        }
        
        $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
    }
    
    // Normalize category path / name for matching: trim, collapse whitespace, lowercase, best-effort transliteration (diacritics)
    private function normalizeCategoryKey($str) {
        $str = (string)$str;
        $str = trim($str);
        if ($str === '') return '';
        
        // collapse whitespace
        $str = preg_replace('/\s+/', ' ', $str);
        
        // lowercase
        if (function_exists('mb_strtolower')) {
            $str = mb_strtolower($str, 'UTF-8');
        } else {
            $str = strtolower($str);
        }
        
        // transliterate diacritics (best effort)
        if (function_exists('iconv')) {
            $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
            if ($tmp !== false) {
                $str = $tmp;
            }
        }
        
        return $str;
    }
    
    // Build full paths for MP categories, similar to OC paths. * Adds 'mp_path' to each category.
    private function buildMpCategoriesWithPath($mpCategories) {
        $byId = array();
        
        if(empty($mpCategories) || !is_array($mpCategories)){
            return $byId;
        }
        
        foreach ($mpCategories as $row) {
            if (!isset($row['id'])) continue;
            $id = (int)$row['id'];
            $byId[$id] = $row;
        }
        
        $pathCache = array();
        
        $buildPath = function($id) use (&$byId, &$pathCache, &$buildPath) {
            $id = (int)$id;
            if (isset($pathCache[$id])) {
                return $pathCache[$id];
            }
            if (!isset($byId[$id])) {
                return '';
            }
            
            $row      = $byId[$id];
            $name     = isset($row['name']) ? $row['name'] : '';
            $parentId = !empty($row['parent_id']) ? (int)$row['parent_id'] : 0;
            
            if ($parentId && isset($byId[$parentId])) {
                $parentPath = $buildPath($parentId);
                $path = $parentPath ? ($parentPath . ' > ' . $name) : $name;
            } else {
                $path = $name;
            }
            
            $pathCache[$id] = $path;
            return $path;
        };
        
        foreach ($byId as $id => $row) {
            $row['mp_path'] = $buildPath($id);
            $byId[$id] = $row;
        }
        
        return array_values($byId);
    }
    
    // map OC category status (0/1) to MP status string.
    private function mapOcCategoryStatusToMp($ocStatus) {
        return $ocStatus ? 'active' : 'inactive';
    }
    
    // Compute OC vs MP category diff + status.
    private function computeCategorySyncStatus() {
        
        $set = $this->mpApiSettings();
        
        $this->load->model('tool/sdx_export_to_mp_sync');
        
        // ---- Fetch MP categories (from cache or API) ----
        $mpcategories = $this->mpGetAllCategories(false);
        
        if (empty($mpcategories['success'])) {
            //throw new Exception('Could not fetch MerchantPro categories.');
            if(isset($this->session->data['error'])){
                $this->session->data['error'] .= '<br>'.$this->language->get('error_mp_categories_api').(isset($mpcategories['error']) ? '<br>'.$mpcategories['error'] : '');
            } else {
                $this->session->data['error'] = $this->language->get('error_mp_categories_api').(isset($mpcategories['error']) ? '<br>'.$mpcategories['error'] : '');
            }
            
        }
        
        if(strpos($mpcategories['source'], DIR_LOGS) !== false) {
            $mpsource = 'Merchantpro Categories from Local Cache';
            $mpfile = $mpcategories['source'];
        } elseif($mpcategories['source'] != 'none') { 
            $mpsource = 'Merchantpro Categories from Remote API';
            $mpfile = null;
        } else {
            $mpsource = null;
            $mpfile = null;
        }
        
        $mpCats = $this->buildMpCategoriesWithPath($mpcategories['mp_categories']);
        
        // ---- Fetch OC categories with path ----
        $ocCats = $this->model_tool_sdx_export_to_mp_sync->getCategoriesWithPath();
        
        // ---- Precompute normalized fields for OC ----
        foreach ($ocCats as &$row) {
            $path = isset($row['path']) ? $row['path'] : $row['name'];
            $row['path']      = $path;
            $row['path_norm'] = $this->normalizeCategoryKey($path);
            $row['name_norm'] = $this->normalizeCategoryKey($row['name']);
            
            $pos        = strrpos($path, ' > ');
            $parentPath = ($pos !== false) ? substr($path, 0, $pos) : '';
            $row['parent_path']      = $parentPath;
            $parentKeyRaw            = ($parentPath === '') ? '__ROOT__' : $parentPath;
            $row['parent_path_norm'] = $this->normalizeCategoryKey($parentKeyRaw);
            
            $row['level']       = ($path === '') ? 0 : (substr_count($path, '>') + 1);
            $row['category_id'] = (int)$row['category_id'];
            $row['parent_id']   = (int)$row['parent_id'];
            $row['status']      = (int)$row['status'];
        }
        unset($row);
        
        // ---- Precompute normalized fields for MP ----
        foreach ($mpCats as &$row) {
            $mpPath = isset($row['mp_path']) ? $row['mp_path'] : (isset($row['name']) ? $row['name'] : '');
            $row['mp_path']   = $mpPath;
            $row['path_norm'] = $this->normalizeCategoryKey($mpPath);
            $row['name_norm'] = $this->normalizeCategoryKey(isset($row['name']) ? $row['name'] : '');
            
            $pos        = strrpos($mpPath, ' > ');
            $parentPath = ($pos !== false) ? substr($mpPath, 0, $pos) : '';
            $row['parent_path']      = $parentPath;
            $parentKeyRaw            = ($parentPath === '') ? '__ROOT__' : $parentPath;
            $row['parent_path_norm'] = $this->normalizeCategoryKey($parentKeyRaw);
            
            $row['level']     = ($mpPath === '') ? 0 : (substr_count($mpPath, '>') + 1);
            $row['id']        = (int)$row['id'];
            $row['parent_id'] = isset($row['parent_id']) ? (int)$row['parent_id'] : 0;
        }
        unset($row);
        
        // ---- Build MP indexes ----
        $mpById            = array();
        $mpByPathNorm      = array();
        $mpByParentAndName = array();
        $mpByParent        = array();
        $mpByName          = array();
        
        foreach ($mpCats as $row) {
            $id           = $row['id'];
            $mpById[$id]  = $row;
            
            if ($row['path_norm'] !== '') {
                if (!isset($mpByPathNorm[$row['path_norm']])) {
                    $mpByPathNorm[$row['path_norm']] = array();
                }
                $mpByPathNorm[$row['path_norm']][] = $id;
            }
            
            $kPN = $row['parent_path_norm'] . '||' . $row['name_norm'];
            if (!isset($mpByParentAndName[$kPN])) {
                $mpByParentAndName[$kPN] = array();
            }
            $mpByParentAndName[$kPN][] = $id;
            
            if (!isset($mpByParent[$row['parent_path_norm']])) {
                $mpByParent[$row['parent_path_norm']] = array();
            }
            $mpByParent[$row['parent_path_norm']][] = $id;
            
            if (!isset($mpByName[$row['name_norm']])) {
                $mpByName[$row['name_norm']] = array();
            }
            $mpByName[$row['name_norm']][] = $id;
        }
        
        // ---- Matching OC -> MP (staged) ----
        $ocToMp = array(); // oc_category_id => ['mp_id'=>..., 'reason'=>...]
        $mpUsed = array(); // mp_id => true
        
        // Stage 1: Exact path match
        foreach ($ocCats as $row) {
            $cid = $row['category_id'];
            if (isset($ocToMp[$cid])) continue;
            
            $pathNorm = $row['path_norm'];
            if ($pathNorm === '') continue;
            
            if (isset($mpByPathNorm[$pathNorm]) && count($mpByPathNorm[$pathNorm]) === 1) {
                $mpId = $mpByPathNorm[$pathNorm][0];
                if (!isset($mpUsed[$mpId])) {
                    $ocToMp[$cid] = array('mp_id' => $mpId, 'reason' => 'EXACT_PATH');
                    $mpUsed[$mpId] = true;
                }
            }
        }
        
        // Stage 2: Same parent + same name
        foreach ($ocCats as $row) {
            $cid = $row['category_id'];
            if (isset($ocToMp[$cid])) continue;
            
            $kPN = $row['parent_path_norm'] . '||' . $row['name_norm'];
            if (isset($mpByParentAndName[$kPN]) && count($mpByParentAndName[$kPN]) === 1) {
                $mpId = $mpByParentAndName[$kPN][0];
                if (!isset($mpUsed[$mpId])) {
                    $ocToMp[$cid] = array('mp_id' => $mpId, 'reason' => 'SAME_PARENT_SAME_NAME');
                    $mpUsed[$mpId] = true;
                }
            }
        }
        
        // Stage 3: Same parent, different name (rename case)
        foreach ($ocCats as $row) {
            $cid = $row['category_id'];
            if (isset($ocToMp[$cid])) continue;
            
            $parentKey = $row['parent_path_norm'];
            if (!isset($mpByParent[$parentKey])) continue;
            
            $candidates = array();
            foreach ($mpByParent[$parentKey] as $mpId) {
                if (!isset($mpUsed[$mpId])) {
                    $candidates[] = $mpId;
                }
            }
            
            if (count($candidates) === 1) {
                $mpId = $candidates[0];
                $ocToMp[$cid] = array('mp_id' => $mpId, 'reason' => 'RENAME_SAME_PARENT');
                $mpUsed[$mpId] = true;
            }
        }
        
        // Stage 4: Same name, different parent (move case)
        foreach ($ocCats as $row) {
            $cid = $row['category_id'];
            if (isset($ocToMp[$cid])) continue;
            
            $nameKey = $row['name_norm'];
            if (!isset($mpByName[$nameKey])) continue;
            
            $candidates = array();
            foreach ($mpByName[$nameKey] as $mpId) {
                if (!isset($mpUsed[$mpId])) {
                    $candidates[] = $mpId;
                }
            }
            
            if (count($candidates) === 1) {
                $mpId = $candidates[0];
                $ocToMp[$cid] = array('mp_id' => $mpId, 'reason' => 'MOVE_SAME_NAME');
                $mpUsed[$mpId] = true;
            }
        }
        
        // ---- Build PATCH / POST / DELETE arrays + per-OC status ----
        $patch    = array();
        $post     = array();
        $delete   = array();
        $ocStatus = array(); // oc_category_id => [mp_id, mp_path, code, label]
        
        // Helper: map OC parent to MP parent id for payload
        $getTargetParentMpId = function($ocParentId) use (&$ocToMp) {
            $ocParentId = (int)$ocParentId;
            if ($ocParentId > 0 && isset($ocToMp[$ocParentId])) {
                return (int)$ocToMp[$ocParentId]['mp_id'];
            }
            return 0;
        };
        
        // Helper: translate our status code to human label
        $labelForCode = function($code, $default = '') {
            switch ($code) {
                case 'ok':
                    return $this->language->get('text_mp_cat_status_ok');
                case 'only_oc':
                    return $this->language->get('text_mp_cat_status_only_oc');
                case 'only_mp':
                    return $this->language->get('text_mp_cat_status_only_mp');
                case 'patch_name':
                    return $this->language->get('text_mp_cat_status_patch_name');
                case 'patch_parent':
                    return $this->language->get('text_mp_cat_status_patch_parent');
                case 'patch_status':
                    return $this->language->get('text_mp_cat_status_patch_status');
                case 'patch_complex':
                    return $this->language->get('text_mp_cat_status_patch_complex');
            }
            return $default;
        };
        
        // OC side: evaluate each category
        foreach ($ocCats as $row) {
            $cid         = $row['category_id'];
            $path        = $row['path'];
            $parentPath  = $row['parent_path'];
            $ocStatusStr = $this->mapOcCategoryStatusToMp($row['status']);
            
            // Before building payloads, derive description, meta_title, meta_description + meta_fields for cat_thumb (name, maybe id?)
            $description = $this->model_tool_sdx_export_to_mp_sync->cleanhtml($row['description']);
            $description .= (isset($row['description_seo']) ? '<hr>'.$this->model_tool_sdx_export_to_mp_sync->cleanhtml($row['description_seo']) : '');
            
            $metaTitle = !empty($row['meta_title'])  ? $row['meta_title'] : $row['name'];
            
            $metaDescription = !empty($row['meta_description'])  ? $row['meta_description'] : '';
            
            // meta_fields (object?) can hold the 'cat_thumb' or 'menu_image' array(s) featuring 'name' key with image url 
            // hopefully this will be handled by MP API so we can have category image (uploaded into MP)
            $metaFields = isset($row['image_url']) ? array('cat_thumb' => array('name' => $row['image_url'])) : array();
            
            if (isset($ocToMp[$cid])) {
                // ---- MAPPED: may be OK or PATCH ----
                $mpId   = $ocToMp[$cid]['mp_id'];
                $reason = $ocToMp[$cid]['reason'];
                
                if (!isset($mpById[$mpId])) {
                    // should not happen, but be safe
                    continue;
                }
                $mpRow = $mpById[$mpId];
                
                $nameDiff   = ($row['name_norm']        !== $mpRow['name_norm']);
                $parentDiff = ($row['parent_path_norm'] !== $mpRow['parent_path_norm']);
                $statusDiff = ($ocStatusStr             !== $mpRow['status']);
                
                // Determine code
                if (!$nameDiff && !$parentDiff && !$statusDiff) {
                    $code = 'ok';
                } elseif ($nameDiff && !$parentDiff && !$statusDiff) {
                    $code = 'patch_name';
                } elseif (!$nameDiff && $parentDiff && !$statusDiff) {
                    $code = 'patch_parent';
                } elseif (!$nameDiff && !$parentDiff && $statusDiff) {
                    $code = 'patch_status';
                } else {
                    $code = 'patch_complex';
                }
                $label = $labelForCode($code, $code);
                
                // Always record status for OC row
                $ocStatus[$cid] = array(
                    'mp_id'   => $mpId,
                    'mp_name' => $mpRow['name'],
                    'mp_path' => $mpRow['mp_path'],
                    'code'    => $code,
                    'label'   => $label,
                );
                
                // If something differs -> add PATCH item
                //if ($code !== 'ok') {
                    $targetParentMpId = $getTargetParentMpId($row['parent_id']);
                    $patch[] = array(
                        'reason'          => $reason,
                        'mp_id'           => $mpId,
                        'oc_category_id'  => $cid,
                        'paths'           => array(
                            'oc'        => $path,
                            'mp'        => $mpRow['mp_path'],
                            'oc_parent' => $parentPath,
                            'mp_parent' => $mpRow['parent_path'],
                        ),
                        'differences'     => array(
                            'name'        => $nameDiff   ? array('oc' => $row['name'],  'mp' => $mpRow['name'])         : null,
                            'parent_path' => $parentDiff ? array('oc' => $parentPath,    'mp' => $mpRow['parent_path']) : null,
                            'status'      => $statusDiff ? array('oc' => $ocStatusStr,   'mp' => $mpRow['status'])      : null,
                        ),
                        // Payload for PATCH /api/v2/categories/{id}
                        'payload' => array(
                            'id'                => $mpId,
                            'parent_id'         => $targetParentMpId,
                            'name'              => $row['name'],
                            
                            'description'       => $description,
                            'meta_title'        => $metaTitle,
                            'meta_description'  => $metaDescription,
                            'page_template'     => 'mixed', // template type used in the case of categories that contain subcategories, values: (products, subcategories, mixed, highlights)
                            
                            //'meta_fields'     => $metaFields, // needs corrected, currently not accepted by MP API
                            
                            'status'    => $ocStatusStr,
                        ),
                    );
                //}
                
            } else {
                // ---- ONLY IN OC: POST candidate ----
                $code  = 'only_oc';
                $label = $labelForCode($code, $code);
                
                $parentMpId = $getTargetParentMpId($row['parent_id']);
                
                if(empty($mpcategories['error'])) {
                    
                    $ocStatus[$cid] = array(
                        'mp_id'   => 0,
                        'mp_path' => '',
                        'mp_name' => '',
                        'code'    => $code,
                        'label'   => $label,
                    );
                    
                    $post[] = array(
                        'oc_category_id' => $cid,
                        'oc_parent_id'   => $row['parent_id'],
                        'parent_path'    => $parentPath,
                        'parent_mp_id'   => $parentMpId,
                        'name'           => $row['name'],
                        'path'           => $path,
                        'status'         => $ocStatusStr,
                        // Payload for POST /api/v2/categories
                        'payload'        => array(
                            'parent_id' => $parentMpId,
                            'name'      => $row['name'],
                            
                            'description'       => $description,
                            'meta_title'        => $metaTitle,
                            'meta_description'  => $metaDescription,
                            'page_template'     => 'mixed', // template type used in the case of categories that contain subcategories, values: (products, subcategories, mixed, highlights)
                            
                            //'meta_fields'     => $metaFields, // needs corrected, currently not accepted by MP API
                            
                            'status'    => $ocStatusStr,
                        ),
                    );
                // mp api/cache error -> no oc matching and no post -> check api settings
                } else {
                    $ocStatus[$cid] = array(
                        'mp_id'   => 0,
                        'mp_name' => '',
                        'mp_path' => '',
                        'code'    => 'api_error',
                        'label'   => 'api_error', // $labelForCode('api_error', 'patch_complex'),
                    );
                    $post = null;
                }
            }
        }
        
        // ---- MP-only: DELETE candidates ----
        foreach ($mpCats as $row) {
            $mpId = $row['id'];
            if (isset($mpUsed[$mpId])) {
                continue;
            }
            $delete[] = array(
                'mp_id'     => $mpId,
                'parent_id' => $row['parent_id'],
                'name'      => isset($row['name']) ? $row['name'] : '',
                'path'      => $row['mp_path'],
                'status'    => isset($row['status']) ? $row['status'] : null,
                'url'       => isset($row['url']) ? $row['url'] : null,
                'mp_category_sync_status_code'  => 'only_mp',
                'mp_category_sync_status_text'  => $labelForCode('only_mp', 'only_mp'),
            );
        }
        
        // ---- Build enriched OC rows ----
        $ocWithStatus = array();
        foreach ($ocCats as $row) {
            $cid = $row['category_id'];
            
            if (isset($ocStatus[$cid])) {
                $st = $ocStatus[$cid];
            } else {
                // Fallback: treat as only_oc if somehow missing
                $st = array(
                    'mp_id'   => 0,
                    'mp_name' => '',
                    'mp_path' => '',
                    'code'    => 'only_oc',
                    'label'   => $labelForCode('only_oc', 'only_oc'),
                    //'code'    => 'no-feed',
                    //'label'   => 'no-mp-cat-feed',
                );
            }
            
            $row['mp_category_id']                 = $st['mp_id'];
            $row['mp_category_name']               = $st['mp_name'];
            $row['mp_category_path']               = $st['mp_path'];
            $row['mp_category_sync_status_code']   = $st['code'];   // e.g. ok, only_oc, patch_name, ...
            $row['mp_category_sync_status_text']   = $st['label'];  // human friendly label
            
            $ocWithStatus[] = $row;
        }
        
        // ---- generate JSON files for API actions ---- 
        $time     = date('c');
        //$baseName = DIR_LOGS . $set['store_slug'] . '_mp-export_categories-sync_' . date('Y-m-d');
        $baseName = DIR_LOGS . $set['store_slug'] . '_mp-export_categories-sync_';
        
        // remove previous sync files for this date/store
        $pattern = $baseName . '*_*.json';
        foreach (glob($pattern) as $oldFile) { @unlink($oldFile); }
        
        $files = array();
        $sets  = array(
            'patch'  => isset($patch) ? $patch : array(),
            'post'   => isset($post) ? $post : array(),
            'delete' => isset($delete) ? $delete : array(),
        );
        foreach ($sets as $action => $rows) {
            if(!empty($rows)) {
                $filename = $baseName . strtoupper($action) . '_' . date('Y-m-d') . '.json';
                $data     = array(
                    'generated_at' => $time,
                    'store_slug'   => $set['store_slug'],
                    'action'       => strtoupper($action), // PATCH / POST / DELETE
                    'total'        => count($rows),
                    'items'        => $rows,
                );
                
                file_put_contents(
                    $filename,
                    json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                );
                
                $files[$action] = $filename;
            }
            else {
                $files[$action] = null;
            }
        }
        
        $mpjsonfiles = array(
            'files'  => $files,
            'counts' => array(
                'patch'  => isset($patch) ? count($patch) : 0,
                'post'   => isset($post) ? count($post) : 0,
                'delete' => isset($delete) ? count($delete) : 0,
            ),
        );
        
        return array(
            'oc_with_status'    => $ocWithStatus,
            'mp_only'           => $delete,
            'patch_items'       => $patch,
            'post_items'        => $post,
            'delete_items'      => $delete,
            'mp_total'          => isset($mpcategories['total']) ? (int)$mpcategories['total'] : count($mpCats),
            'mp_cache'          => $mpcategories['mp_catcache'],
            'mp_source'         => $mpsource,
            'mp_file'           => $mpfile,
            'mp_json_files'     => $mpjsonfiles,
        );
    }
    
    // MP API actions/sync for categories
    public function applyMpCategoriesSync() {
        $this->load->language('tool/sdx_export_to_mp_sync');
        $this->load->model('setting/setting');
        $this->load->model('tool/sdx_export_to_mp_sync');
        
        if (!$this->user->hasPermission('modify', 'tool/sdx_export_to_mp_sync')) {
            $this->session->data['error'] = $this->language->get('error_permission');
            $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
        }
        
        $action = isset($this->request->post['sync_action']) ? strtolower($this->request->post['sync_action']) : '';
        if (!in_array($action, array('patch', 'post', 'delete'))) {
            $this->session->data['error'] = 'Unknown categories sync action: ' . $action;
            $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
        }
        
        // Helper: get a single ID from POST (force button) or multiple (batch)
        $getIds = function($singleKey, $multiKey) {
            $ids = array();
            if (!empty($this->request->post[$singleKey])) {
                $ids[] = (int)$this->request->post[$singleKey];
            } elseif (!empty($this->request->post[$multiKey]) && is_array($this->request->post[$multiKey])) {
                foreach ($this->request->post[$multiKey] as $id) {
                    $ids[] = (int)$id;
                }
            }
            return $ids;
        };
        
        $ok   = 0;
        $fail = 0;
        $errorDetails = array();
        
        try {
            
            $plan = $this->loadCategorySyncPlanFromJson($action);
            
            // Index by OC category id or MP id depending on action
            $byOcId = array();
            $byMpId = array();
            
            if ($action === 'patch' || $action === 'post') {
                foreach ($plan['items'] as $item) {
                    if (!empty($item['oc_category_id'])) {
                        $byOcId[(int)$item['oc_category_id']] = $item;
                    }
                }
                unset($item);
            } elseif ($action === 'delete') {
                foreach ($plan['items'] as $item) {
                    if (!empty($item['mp_id'])) {
                        $byMpId[(int)$item['mp_id']] = $item;
                    }
                }
                unset($item);
            }
            
            if ($action === 'patch') {
                $ids = $getIds('oc_category_id', 'oc_category_ids');
                if (!$ids) {
                    // If nothing specific, you *could* run batch; for now, do nothing.
                    // $ids = array_keys($byOcId);
                }
                
                foreach ($ids as $cid) {
                    if (!isset($byOcId[$cid])) {
                        $fail++;
                        $errorDetails[] = 'no PATCH, error: OC category ID ' . $cid . ' is not in JSON cache file: '.$plan['filename'].'.';
                        continue;
                    }
                    $item    = $byOcId[$cid];
                    $payload = !empty($item['payload']) ? $item['payload'] : null;
                    
                    if (!$payload || empty($payload['id'])) {
                        $fail++;
                        $errorDetails[] = 'no PATCH, error: OC category ID ' . $cid . ' has no valid payload or MP ID.';
                        continue;
                    }
                    
                    $mpId  = (int)$payload['id'];
                    $resp  = $this->mpRequest('PATCH', '/api/v2/categories/' . $mpId, array(), $payload);
                    
                    if ($resp['status'] >= 200 && $resp['status'] < 300) {
                        $ok++;
                    } else {
                        $fail++;
                        $msg = 'API responded: HTTP ' . (int)$resp['status'] . ' -> ' . (!empty($resp['error']) ? $resp['error'] : '');
                        $errorDetails[] = 'PATCH try for OC category ID ' . $cid . ' as MP category ID ' . $mpId . ' failed: ' . $msg;
                    }
                }
                unset($ids); unset($cid); unset($mpId);
                
            } elseif ($action === 'post') {
                $ids = $getIds('oc_category_id', 'oc_category_ids');
                if (!$ids) {
                    // Again, you could allow "POST all" if you want:
                    // $ids = array_keys($byOcId);
                }
                
                foreach ($ids as $cid) {
                    if (!isset($byOcId[$cid])) {
                        $fail++;
                        $errorDetails[] = 'no POST, error: OC category ID ' . $cid . ' is not in JSON cache file: '.$plan['filename'].'.';
                        continue;
                    }
                    $item    = $byOcId[$cid];
                    $payload = !empty($item['payload']) ? $item['payload'] : null;
                    
                    if (!$payload) {
                        $fail++;
                        $errorDetails[] = 'no POST, error: OC category ID ' . $cid . ' :: '.$item['name'].' has no payload.';
                        continue;
                    }
                    
                    // ---- IMPORTANT: parent check for sub-categories ----
                    $parentMpId   = isset($payload['parent_id']) ? (int)$payload['parent_id'] : 0;
                    $parentPath   = !empty($item['parent_path']) ? $item['parent_path'] : '';
                    if ($parentPath !== '' && $parentMpId <= 0) {
                        // We have a subcategory but no MP parent mapped.
                        $fail++;
                        $errorDetails[] = 
                            'no POST, error: OC category ' . $cid . ' :: '.$item['name'].' '.
                            ' cannot be created because its MP parent category is missing.' .
                            'Please sync the MP parent category first -> : <b>' . $parentPath . '</b>.';
                        continue;
                    }
                    
                    $resp = $this->mpRequest('POST', '/api/v2/categories', array(), $payload);
                    
                    if ($resp['status'] >= 200 && $resp['status'] < 300) {
                        $ok++;
                    } else {
                        $fail++;
                        $msg = 'API responded: HTTP ' . (int)$resp['status'] . ' -> ' . (!empty($resp['error']) ? $resp['error'] : '');
                        $errorDetails[] = 'POST try for OC category ID ' . $cid . ' failed: ' . $msg;
                    }
                }
                unset($ids); unset($cid);
                
            } elseif ($action === 'delete') {
                $ids = $getIds('mp_category_id', 'mp_category_ids');
                if (!$ids) {
                    // Or: $ids = array_keys($byMpId);
                }
                
                foreach ($ids as $mpId) {
                    if (!isset($byMpId[$mpId])) {
                        // Not fatal, but log it for clarity
                        $errorDetails[] = 'no DELETE, error: MP category ID ' . $mpId . ' not present in JSON cache file: '.$plan['filename'].'.';
                    }
                    
                    $resp = $this->mpRequest('DELETE', '/api/v2/categories/' . $mpId, array(), null);
                    
                    if ($resp['status'] >= 200 && $resp['status'] < 300) {
                        $ok++;
                    } else {
                        $fail++;
                        $msg = 'API responded: HTTP ' . (int)$resp['status'] . ' -> ' . (!empty($resp['error']) ? $resp['error'] : '');
                        $errorDetails[] = 'DELETE try for MP category ID ' . $mpId . ' failed: ' . $msg;
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->session->data['error'] = 'Error while applying MP categories sync: ' . $e->getMessage();
            $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
            return;
        }
    
        if ($fail === 0) {
            $this->session->data['success'] = sprintf('MP categories %s OK: %d.', strtoupper($action), $ok);
        } else {
            $msg = sprintf('MP categories %s partial: OK=%d, Fail=%d.', strtoupper($action), $ok, $fail);
            if (!empty($errorDetails)) {
                $msg .= '<br>' . implode('<br>', array_slice($errorDetails, 0, 10));
            }
            $this->session->data['error'] = $msg;
        }
        // test, collect & show API response status and info... 
        //if( isset($this->session->data['error']) ) {
        //    $this->session->data['error'] .= '<br> API response -> status: '.(isset($resp['status']) ? $resp['status'] : 'no-response-status').' error: '.(isset($resp['error']) ? $resp['error'] : 'no-response-error').' <hr>';
        //    $this->session->data['error'] .= 'payload: ' . (isset($payload) ? implode(' , ', $payload) : 'no-payload');
        //}
        //else {
        //    $this->session->data['error'] = '<br> API response -> status: '.(isset($resp['status']) ? $resp['status'] : 'no-response-status').' error: '.(isset($resp['error']) ? $resp['error'] : 'no-response-error').' <hr>';
        //    $this->session->data['error'] .= 'payload: ' . (isset($payload) ? implode(' , ', $payload) : 'no-payload');
        //}
    
        //$this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
        $this->redirect($this->url->link('tool/sdx_export_to_mp_sync/buildMPallCategoriesCache', 'token=' . $this->session->data['token'], 'SSL'));
    }
    
    // helper to load PATCH / POST / DELETE category plan from JSON files generated by computeCategorySyncStatus().
    private function loadCategorySyncPlanFromJson($action) {
        $set = $this->mpApiSettings();
        $storeSlug = $set['store_slug'];
        
        $action = strtolower($action); // patch|post|delete
        if (!in_array($action, array('patch', 'post', 'delete'))) {
            return array('items' => array(), 'filename' => null);
        }
        
        $baseName = DIR_LOGS . $storeSlug . '_mp-export_categories-sync_';
        $pattern  = $baseName . strtoupper($action) . '_*.json';
        
        $files = glob($pattern);
        if (!$files) {
            return array('items' => array(), 'filename' => null);
        }
        
        // Latest by mtime
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $filename = $files[0];
        
        $raw = file_get_contents($filename);
        if ($raw === false) {
            return array('items' => array(), 'filename' => $filename);
        }
        
        $json = json_decode($raw, true);
        if (!is_array($json) || !isset($json['items']) || !is_array($json['items'])) {
            return array('items' => array(), 'filename' => $filename);
        }
        
        return array(
            'items'    => $json['items'],
            'filename' => $filename,
        );
    }
    
    // get MP taxes using API and creates cache JSON file like DIR_LOGS . {store_slug}_mp-export_taxes-cache_{date('Y-m-d')}.json
    private function mpGetTaxes($writeFile = true) {
        $set = $this->mpApiSettings();
        
        $resp = array();
        $resp['generated_at'] = date('c');
        $resp['source'] = 'none';
        
        // cache path -> find latest json cache file (pattern)
        $cachepattern = DIR_LOGS . $set['store_slug'] . '_mp-export_taxes-cache_*.json';
        $cachefiles = glob($cachepattern);
        if ($cachefiles) {
            usort($cachefiles, function($tfa, $tfb) { return filemtime($tfb) - filemtime($tfa); });
            $cachefile = $cachefiles[0];
        } else { $cachefile = DIR_LOGS . $set['store_slug'] . '_mp-export_taxes-cache_' . date('Y-m-d') . '.json'; }
        
        // if cache exists and fresh -> return it, else force rebuild
        if (is_file($cachefile) && !$writeFile ) {
            $cachedata = file_get_contents($cachefile);
            $rawdata = json_decode($cachedata, true);
            if (is_array($rawdata) && isset($rawdata['json'])) {
                $resp['source'] = 'Local Cache File';
                $resp = array_merge($resp, $rawdata);
                return $resp;
            }
        }
        
        $records = $this->mpRequest('GET', '/api/v2/taxonomy/taxes', array('limit'  => 100));
        foreach ($records['json'] as &$r) {
            if (isset($r['id'])) $r['id'] = (int)$r['id'];
            if (isset($r['name'])) $r['name'] = (string)$r['name'];
            if (isset($r['value'])) $r['value'] = (float)$r['value'];
        }
        $resp['source'] = 'Remote API';
        $resp = array_merge($resp, $records);
        if ($writeFile) {
            // delete any existing taxes-cache json file for this store_slug
            $pattern = DIR_LOGS . $set['store_slug'] . '_mp-export_taxes-cache_*.json';
            $files = glob($pattern);
            if ($files) { foreach ($files as $ctf) { @unlink($ctf); } }
            $file = DIR_LOGS . $set['store_slug'] . '_mp-export_taxes-cache_' . date('Y-m-d') . '.json';
            file_put_contents($file, json_encode($resp, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
        }
        return $resp;
    }
    
    // get MP units  using API and creates cache JSON file like DIR_LOGS . {store_slug}_mp-export_units-cache_{date('Y-m-d')}.json
    private function mpGetUnits($writeFile = true) {
        $set = $this->mpApiSettings();
        
        $resp = array();
        $resp['generated_at'] = date('c');
        $resp['source'] = 'none';
        
        // cache path -> find latest json cache file (pattern)
        $cachepattern = DIR_LOGS . $set['store_slug'] . '_mp-export_units-cache_*.json';
        $cachefiles = glob($cachepattern);
        if ($cachefiles) {
            usort($cachefiles, function($ufa, $ufb) { return filemtime($ufb) - filemtime($ufa); });
            $cachefile = $cachefiles[0];
        } else { $cachefile = DIR_LOGS . $set['store_slug'] . '_mp-export_units-cache_' . date('Y-m-d') . '.json'; }
        
        // if cache exists and fresh -> return it, else force rebuild
        if (is_file($cachefile) && !$writeFile ) {
            $cachedata = file_get_contents($cachefile);
            $rawdata = json_decode($cachedata, true);
            //if (is_array($rawdata) && isset($rawdata['json'])) {
            if (is_array($rawdata) ) {
                $resp['source'] = 'Local Cache File';
                $resp = array_merge($resp, $rawdata);
                return $resp;
            }
        }
        
        $records = $this->mpRequest('GET', '/api/v2/taxonomy/measurement_units', array('limit'  => 100));
        $resp['source'] = 'Remote API';
        $resp = array_merge($resp, $records);
        if ($writeFile) {
            // delete any existing taxes-cache json file for this store_slug
            $pattern = DIR_LOGS . $set['store_slug'] . '_mp-export_units-cache_*.json';
            $files = glob($pattern);
            if ($files) { foreach ($files as $cuf) { @unlink($cuf); } }
            $file = DIR_LOGS . $set['store_slug'] . '_mp-export_units-cache_' . date('Y-m-d') . '.json';
            file_put_contents($file, json_encode($resp, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
        }
        return $resp;
    }
    
    // to be called to get/create the taxonomies - taxes, measurement units
    public function mpGetTaxonomies() {
        $this->load->language('tool/sdx_export_to_mp_sync');
        $this->load->model('setting/setting');
        $this->load->model('tool/sdx_export_to_mp_sync');
        $set = $this->mpApiSettings();
        $successDetails = array();
        $errorDetails = array();
        if (!$this->user->hasPermission('modify', 'tool/sdx_export_to_mp_sync')) {
            $this->session->data['error'] = $this->language->get('error_permission');
            $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
        }
        try {
            $taxes = $this->mpGetTaxes(); // use false to read the cache or true/empty to write cache
            $units = $this->mpGetUnits(); // use false to read the cache or true/empty to write cache
            
            if ($taxes['status'] >= 200 && $taxes['status'] < 300) {
                    $successDetails[] = $taxes['source'].' for MP Taxes updated (HTTP '.$taxes['status'].'), '.count($taxes['json']).' items in DIR_LOGS: '.$set['store_slug'].'_mp-export_taxes-cache_*.json ';
            } else {
                    $errorDetails[] = 'GET try for MP Taxes taxonomy failed: API responded HTTP '.$taxes['status'].' -> '.(!empty($taxes['error']) ? $taxes['error'] : '');
            }
            
            if ($units['status'] >= 200 && $units['status'] < 300) {
                    $successDetails[] = $units['source'].' for MP Units updated (HTTP '.$units['status'].'), '.count($units['json']).' items in DIR_LOGS: '.$set['store_slug'].'_mp-export_units-cache_*.json ';
            } else {
                    $errorDetails[] = 'GET try for MP Units taxonomy failed: API responded HTTP '.$units['status'].' -> '.(!empty($units['error']) ? $units['error'] : '');
            }
            
            if(!empty($successDetails)) {
                $this->session->data['success'] = sprintf( $this->language->get('text_json_prepared'), 'MP API response', (count($taxes['json'])+count($units['json'])), '<br>'.implode('<br>', array_slice($successDetails, 0, 5)) );
            }
            
            if(!empty($errorDetails)) {
                $this->session->data['error'] = implode('<br>', array_slice($errorDetails, 0, 5));
            }
            
            //$this->session->data['success'] = sprintf(
            //    $this->language->get('text_json_prepared'), 
            //    'MP API response', 
            //    (count($taxes['json'])+count($units['json'])), 
            //    'in DIR_LOGS: ' . $set['store_slug'].'_mp-export_taxes-cache_*.json & '. $set['store_slug'].'_mp-export_units-cache_*.json'
            //);
            
        } catch (Exception $e) {
            $this->session->data['error'] = 'MP Taxonomies API/cache failed: ' . $e->getMessage();
        }
        
        $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
    }
    
    // MP API actions/sync for products
    public function applyMpProductsSync() {
        $this->load->language('tool/sdx_export_to_mp_sync');
        $this->load->model('setting/setting');
        $this->load->model('tool/sdx_export_to_mp_sync');
        
        if (!$this->user->hasPermission('modify', 'tool/sdx_export_to_mp_sync')) {
            $this->session->data['error'] = $this->language->get('error_permission');
            $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
        }
        
        // Expected: product_action = patch|post (delete optional)
        $action = isset($this->request->post['product_action']) ? strtolower($this->request->post['product_action']) : '';
        if (!in_array($action, array('patch', 'post', 'delete'))) {
            $this->session->data['error'] = 'Unknown products sync action: ' . $action;
            $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
        }
        
        // Helper: get a single ID from POST (force button) or multiple (batch)
        //$getIds = function($singleKey, $multiKey) {
        //    $ids = array();
        //    if (!empty($this->request->post[$singleKey])) {
        //        $ids[] = (int)$this->request->post[$singleKey];
        //    } elseif (!empty($this->request->post[$multiKey]) && is_array($this->request->post[$multiKey])) {
        //        foreach ($this->request->post[$multiKey] as $id) {
        //            $ids[] = (int)$id;
        //        }
        //    }
        //    return $ids;
        //};
        
        // Collect product IDs:
        //  - single "force" button: oc_product_id
        //  - batch (checkboxes):    oc_product_ids[]
        $product_ids = array();
        if (!empty($this->request->post['oc_product_id'])) {
            $product_ids[] = (int)$this->request->post['oc_product_id'];
        }
        if (!empty($this->request->post['oc_product_ids']) && is_array($this->request->post['oc_product_ids'])) {
            foreach ($this->request->post['oc_product_ids'] as $pid) {
                $pid = (int)$pid;
                if ($pid > 0) {
                    $product_ids[] = $pid;
                }
            }
        }
        $product_ids = array_values(array_unique($product_ids));
        
        if (!$product_ids) {
            $this->session->data['error'] = 'No products selected for MP sync.';
            $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
        }
        
        $ok                  = 0;
        $fail                = 0;
        $skipped_missing_cat = 0;
        $errorDetails        = array();
        
        foreach ($product_ids as $product_id) {
            
            // 1) Build OC-side base row for this product
            $ocproduct = $this->model_tool_sdx_export_to_mp_sync->getOcProductRowForMp($product_id);
            
            if (!$ocproduct) {
                $fail++;
                $errorDetails[] = 'OC product ID ' . $product_id . ' not found or not suitable for MP sync.';
                continue;
            }
            
            // 2) Build MP payload via getProductDetailsForMP()
            $details = $this->model_tool_sdx_export_to_mp_sync->getProductDetailsForMP($ocproduct);
            
            if (empty($details['product']) || !is_array($details['product'])) {
                $fail++;
                $msg = !empty($details['error']) ? $details['error'] : 'Unknown error from getProductDetailsForMP';
                $errorDetails[] = 'Product ID ' . $product_id . ': ' . $msg;
                continue;
            }
            
            $mpPayload         = $details['product'];
            
            $missingCategories = !empty($details['missing_categories']) && is_array($details['missing_categories'])
                ? $details['missing_categories']
                : array();
            
            // 3) If there are unmapped categories, skip to avoid sending incomplete category mapping
            if (!empty($missingCategories)) {
                $skipped_missing_cat++;
                $errorDetails[] = 'Product ID ' . $product_id . ' skipped: missing MP mapping for OC categories: ' . implode(', ', $missingCategories);
                continue;
            }
            
            // 4) Decide POST vs PATCH vs DELETE
            $resp = null;
            
            if ($action === 'patch') {
                // Need an existing MP product id
                if (empty($mpPayload['id'])) {
                    $fail++;
                    $errorDetails[] = 'PATCH product ID ' . $product_id . ' skipped: no MP product ID (id) present.';
                    continue;
                }
                
                $mpId = (int)$mpPayload['id'];
                
                // For PATCH we can keep or unset 'id' in the body; usually not required in payload.
                unset($mpPayload['id']);
                
                $resp = $this->mpRequest(
                    'PATCH',
                    '/api/v2/products/' . $mpId,
                    array('ignore_nonexistent_fields' => 1),
                    $mpPayload
                );
                
            } elseif ($action === 'post') {
                // For POST we must not send id
                if (isset($mpPayload['id'])) {
                    unset($mpPayload['id']);
                }
                
                $resp = $this->mpRequest(
                    'POST',
                    '/api/v2/products',
                    array('ignore_nonexistent_fields' => 1),
                    $mpPayload
                );
                
            } elseif ($action === 'delete') {
                // Only allowed if we know MP id
                if (empty($mpPayload['id'])) {
                    $fail++;
                    $errorDetails[] = 'DELETE product ID ' . $product_id . ' skipped: no MP product ID present.';
                    continue;
                }
                
                $mpId = (int)$mpPayload['id'];
                
                $resp = $this->mpRequest(
                    'DELETE',
                    '/api/v2/products/' . $mpId,
                    array(),
                    null
                );
            }
            
            if (!is_array($resp)) {
                $fail++;
                $errorDetails[] = 'MP API ' . strtoupper($action) . ' returned invalid response for Product ID ' . $product_id . ' .';
                continue;
            }
            
            $status = isset($resp['status']) ? (int)$resp['status'] : 0;
            
            if ($status >= 200 && $status < 300) {
                $ok++;
            } else {
                $fail++;
                
                $msg = 'HTTP ' . $status;
                // Try to extract MP API error message if present
                if (!empty($resp['json']['message'])) {
                    $msg .= ' -> ' . $resp['json']['message'];
                } elseif (!empty($resp['json']['error'])) {
                    $msg .= ' -> ' . $resp['json']['error'];
                }
                
                $errorDetails[] = strtoupper($action) . ' product #' . $product_id . ': ' . $msg;
            }
        }
        
        // Build summary messages for UI
        if ($fail === 0 && $skipped_missing_cat === 0) {
            $this->session->data['success'] = sprintf(
                'MP API %s was OK for %d products.',
                strtoupper($action),
                $ok
            );
        } else {
            $msg = sprintf(
                'MP API %s was OK for %d products, but Failed for %d products and Skipped for %d products (due to missing categories).',
                strtoupper($action),
                $ok,
                $fail,
                $skipped_missing_cat
            );
            
            if (!empty($errorDetails)) {
                // Avoid overloading the UI – show first few lines
                $msg .= '<br>' . implode('<br>', array_slice($errorDetails, 0, 10));
            }
            
            $this->session->data['error'] = $msg;
        }
        
        // Finally, go back to tool main page
        $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
    }
    
    
    // skeletons
    // called by template's JS when individual/batch product(s) are selected for import
    // returns a json encoded array, can be used otherwise
    public function exportProducts() {
        
        //$this->load->language('tool/sdx_export_to_mp_sync');
        
        $json = array();
        
        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            
            //$selected = array();
            
            if (!empty($this->request->post['selected_json'])) {
                $selected = json_decode($this->request->post['selected_json'], true);
                //$selected = str_replace(['"','[',']'], '', $this->request->post['selected_json']);
                //$selected = explode(',', $this->request->post['selected_json']);
            }
            //if (!is_array($selected)) {
            else {
                $selected = array();
            }
            
            if (is_array($selected)) {
                // For now, just return them so we can test
                $json['success'] = true;
                $json['export_count'] = count($selected);
                $json['ids'] = $selected;
            } else {
                $json['error'] = 'Products selected, but corrupted data! data: '.print_r($selected, true);
            }
            
        } else {
            $json['error'] = 'Invalid request';
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function export() {
        
        $this->load->language('tool/sdx_export_to_mp_sync');
        $this->load->model('tool/sdx_export_to_mp_sync');
        
        if (!$this->user->hasPermission('modify', 'tool/sdx_export_to_mp_sync')) {
            $this->session->data['error'] = $this->language->get('error_permission');
            $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
        }
        
        $products = $this->model_tool_sdx_export_to_mp_sync->checkOCagainstMP(array());
        if(!empty($products['error'])) { $this->session->data['error'] = $products['error']; }
        
        // For now just inform user how many rows would be exported
        $count = count($products);
        $this->session->data['success'] = sprintf('%d product rows prepared for export (not yet written).', $count);
        
        $this->redirect($this->url->link('tool/sdx_export_to_mp_sync', 'token=' . $this->session->data['token'], 'SSL'));
    }
    
}

?>