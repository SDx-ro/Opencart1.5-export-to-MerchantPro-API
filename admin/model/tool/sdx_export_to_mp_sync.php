<?php

/* v1.5 model SDxExportToMPSync */

class ModelToolSdxExportToMPSync extends Model {
    
    // get total OC products, filtered or not, for pagination or elsewhere
    public function getTotalProducts($filter = array()) {
        $sql = "SELECT COUNT(DISTINCT p.product_id) AS total
                FROM " . DB_PREFIX . "product p
                LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
                WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' ";
                
        if (isset($filter['product_status']) && $filter['product_status'] !== '') {
            $sql .= " AND p.status = '" . (int)$filter['product_status'] . "'";
        }
        
        if (!empty($filter['product_category']) && is_array($filter['product_category'])) {
            $cats = implode(',', array_map('intval', $filter['product_category']));
            $sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_to_category pc WHERE pc.product_id = p.product_id AND pc.category_id IN (" . $cats . "))";
        }
        
        $query = $this->db->query($sql);
        return (int)$query->row['total'];
    }
    
    // get OC products, filtered or not, paginated or not, used by getProductsForMP
    public function getProducts($filter = array()) {
        
        $sql = "SELECT p.product_id, p.image, p.status, p.model, p.sku, p.ean, p.quantity, p.stock_status_id, p.price, pd.name 
                FROM " . DB_PREFIX . "product p
                LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
                WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' ";
        
        if (isset($filter['product_status']) && $filter['product_status'] !== '') {
            $sql .= " AND p.status = '" . (int)$filter['product_status'] . "'";
        }
        
        if (!empty($filter['product_category']) && is_array($filter['product_category'])) {
            $cats = implode(',', array_map('intval', $filter['product_category']));
            $sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_to_category pc WHERE pc.product_id = p.product_id AND pc.category_id IN (" . $cats . "))";
        }
        
        $sql .= " GROUP BY p.product_id ORDER BY pd.name ASC";
        
        if (isset($filter['start']) || isset($filter['limit'])) {
            if ($filter['start'] < 0) $filter['start'] = 0;
            if ($filter['limit'] < 1) $filter['limit'] = $this->config->get('config_admin_limit');
            $sql .= " LIMIT " . (int)$filter['start'] . "," . (int)$filter['limit'];
        }
        
        $query = $this->db->query($sql);
        return $query->rows;
    }
    
    // categories with path for filter selector
    public function getCategoriesWithPath() {
        
        $lang_id = (int)$this->config->get('config_language_id');
        
        $sql = "SELECT 
                    c.category_id,
                    c.parent_id,
                    c.status,
                    c.sort_order,
                    c.image,
                    cd.name,
                    cd.description,
                    cd.description_seo,
                    cd.meta_title,
                    cd.meta_description,
                    cd.meta_keyword
                FROM " . DB_PREFIX . "category c
                JOIN " . DB_PREFIX . "category_description cd 
                    ON (c.category_id = cd.category_id)
                WHERE cd.language_id = '" . $lang_id . "'";
    
        $query = $this->db->query($sql);
        
        $rows = $query->rows;
        $cats = array();
        
        foreach ($rows as $r) {
            $cats[$r['category_id']] = array(
                'category_id'       => (int)$r['category_id'],
                'parent_id'         => (int)$r['parent_id'],
                'status'            => (int)$r['status'],
                'sort_order'        => (int)$r['sort_order'],
                'image'             => $r['image'],
                'name'              => $r['name'],
                'description'       => $r['description'],
                'description_seo'   => $r['description_seo'],
                'meta_title'        => $r['meta_title'],
                'meta_description'  => $r['meta_description'],
                'meta_keyword'      => $r['meta_keyword'],
            );
        }
        
        // Build "path" from parent chain
        $build = function($cid) use (&$cats, &$build) {
            if (!isset($cats[$cid])) return '';
            $name = $cats[$cid]['name'];
            if ($cats[$cid]['parent_id'] && isset($cats[$cats[$cid]['parent_id']])) {
                $parent = $build($cats[$cid]['parent_id']);
                if ($parent) return $parent . ' > ' . $name;
            }
            return $name;
        };
        
        // Also add path + (optionally) a ready-made image URL
        // (you can reuse this later in the controller if you prefer)
        $catalog_url = defined('HTTPS_CATALOG')
            ? HTTPS_CATALOG
            : $this->config->get('config_url');
        
        $catalog_url = rtrim($catalog_url, '/') . '/';
        
        $out = array();
        foreach ($cats as $cid => $cinfo) {
            $image_url = '';
            if (!empty($cinfo['image'])) {
                $image_url = $catalog_url . 'image/' . ltrim($cinfo['image'], '/');
            }
            
            $out[] = array(
                'category_id'       => $cid,
                'parent_id'         => $cinfo['parent_id'],
                'status'            => $cinfo['status'],
                'image'             => $cinfo['image'],
                'image_url'         => $image_url,
                'name'              => $cinfo['name'],
                'description'       => $cinfo['description'],
                'description_seo'   => $cinfo['description_seo'],
                'meta_title'        => $cinfo['meta_title'],
                'meta_description'  => $cinfo['meta_description'],
                'meta_keyword'      => $cinfo['meta_keyword'],
                'path'              => $build($cid),
            );
        }
        
        usort($out, function($a, $b) {
            //return strcasecmp($a['name'], $b['name']);
            return strcasecmp($a['path'], $b['path']); // sort by path
        });
        
        return $out;
    }
    
    /* === start of get and update MerchantPro consolidated Feed === */
    // Update/Create MerchantPro merged/consolidated XLSX feed for products
    public function updateMPfeed() {
        
        $current_limit = ini_get('memory_limit');
        $desired_limit = 1 * 1024 * 1024 * 1024; // 1GB, 1.5GB, 2GB in bytes
        if ($this->memoryToBytes($current_limit) < $desired_limit) {
            ini_set('memory_limit', $desired_limit); // Increase to $desired_limit (1GB, 1.5GB, 2GB, ...)
        }
        set_time_limit(60);                // // Prevent timeout but keep safety net (1 minutes)
        //set_time_limit(600);                // // Prevent timeout but keep safety net (10 minutes)
        //ini_set('memory_limit', '1024M'); // or '2G' for really big exports
        //set_time_limit(0);                // prevent timeout
        
        // require libraries
        require_once(DIR_SYSTEM . '/library/SimpleXLSX/SimpleXLSX.php');
        require_once(DIR_SYSTEM . '/library/SimpleXLSXGen/SimpleXLSXGen.php');
        
        // load settings (feed URLs, mp_api_url)
        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSetting('sdx_export_to_mp_sync');
        $api = isset($settings['sdx_export_to_mp_sync_api']) ? $settings['sdx_export_to_mp_sync_api'] : array();
        
        $feed_simple   = isset($api['mp_feed_simple']) ? trim($api['mp_feed_simple']) : (isset($api['mp_feed_simple_url']) ? trim($api['mp_feed_simple_url']) : '');
        $feed_variants = isset($api['mp_feed_variants']) ? trim($api['mp_feed_variants']) : (isset($api['mp_feed_variants_url']) ? trim($api['mp_feed_variants_url']) : '');
        $mp_api_url    = isset($api['mp_api_url']) ? trim($api['mp_api_url']) : '';
        
        if (!$feed_simple || !$feed_variants) {
            return array('success' => false, 'error' => 'Feed URLs not configured (mp_feed_simple / mp_feed_variants).');
        }
        
        // derive store slug (prefer mp_api_url, fallback to feed host)
        $store_slug = $this->deriveStoreSlugFromApi($api);
        if (!$store_slug) $store_slug = $this->deriveStoreSlugFromUrl($feed_simple);
        if (!$store_slug) $store_slug = 'mp-store';
        
        // delete any existing merged feed for this store_slug
        $pattern = DIR_LOGS . $store_slug . '_mp-export_*.xlsx';
        //$pattern = DIR_LOGS . $store_slug . '_mp-export_*.*';
        $files = glob($pattern);
        if ($files) {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
        
        // local feed files
        $local_feed_simple   = DIR_LOGS . $store_slug . '_mp-export_feed-simple_' . date('Y-m-d') . '.xlsx';
        $local_feed_variants = DIR_LOGS . $store_slug . '_mp-export_feed-variants_' . date('Y-m-d') . '.xlsx';
        
        // download with cURL
        try {
            $this->downloadFile($feed_simple, $local_feed_simple);
            $this->downloadFile($feed_variants, $local_feed_variants);
        } catch (Exception $e) {
            // Cleanup partial files before bailing out
            if (is_file($local_feed_simple)) @unlink($local_feed_simple);
            if (is_file($local_feed_variants)) @unlink($local_feed_variants);
            return array('success' => false, 'error' => 'Failed downloading feeds: ' . $e->getMessage());
        }
        
        // parse XLSX feeds
        try {
            //$xlsx1 = Shuchkin\SimpleXLSX::parse($feed_simple); // parse XLSX feed directly from URL
            $xlsx1 = Shuchkin\SimpleXLSX::parse($local_feed_simple); // parse XLSX feed locally
            if (!$xlsx1) {
                $err = method_exists('Shuchkin\SimpleXLSX','parseError') ? Shuchkin\SimpleXLSX::parseError() : 'Unknown parse error (simple feed)';
                return array('success'=>false,'error'=>'Failed parsing simple feed: '.$err);
            }
        } catch (Exception $e) {
            return array('success'=>false,'error'=>'Exception parsing simple feed: '.$e->getMessage());
        }
        
        try {
            //$xlsx2 = Shuchkin\SimpleXLSX::parse($feed_variants); // parse XLSX feed directly from URL
            $xlsx2 = Shuchkin\SimpleXLSX::parse($local_feed_variants); // parse XLSX feed locally
            if (!$xlsx2) {
                $err = method_exists('Shuchkin\SimpleXLSX','parseError') ? Shuchkin\SimpleXLSX::parseError() : 'Unknown parse error (variants feed)';
                return array('success'=>false,'error'=>'Failed parsing variants feed: '.$err);
            }
        } catch (Exception $e) {
            return array('success'=>false,'error'=>'Exception parsing variants feed: '.$e->getMessage());
        }
        
        // rows() returns full sheet (first row = header)
        $rows1 = $xlsx1->rows();
        $rows2 = $xlsx2->rows();
        
        if (empty($rows1) || count($rows1) < 1) {
            return array('success'=>false,'error'=>'Simple feed appears empty or invalid.');
        }
        if (empty($rows2) || count($rows2) < 1) {
            return array('success'=>false,'error'=>'Variants feed appears empty or invalid.');
        }
        
        // headers and data rows
        $header1 = array_map('trim', $rows1[0]);
        $data1 = array_slice($rows1, 1);
        $header2 = array_map('trim', $rows2[0]);
        $data2 = array_slice($rows2, 1);
        
        // helpers
        $normalize = function($s) {
            $s = trim((string)$s);
            if ($s === '') return '';
            if (function_exists('iconv')) {
                $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
                if ($t) $s = $t;
                //if ($t !== false) $s = $t;
            }
            $s = mb_strtolower($s, 'UTF-8');
            // remove punctuation except underscore and dash, collapse whitespace
            $s = preg_replace('/[^\p{L}\p{N}\s\_\-]/u', ' ', $s);
            // keep only one space for string comparison
            $s = preg_replace('/\s+/', ' ', $s);
            // remove space for consistent string comparison // comment it to disable
            $s = preg_replace('/\s+/', '', $s);
            // trim at the end of string
            $s = trim($s, " \t\n\r\0\x0B\xC2\xA0\"'`");
            return $s;
        };
        
        $findHeaderIndex = function($headerArray, $candidates) use ($normalize) {
            foreach ($headerArray as $i => $col) {
                $hn = $normalize($col);
                foreach ($candidates as $cand) {
                    $cn = $normalize($cand);
                    // extended comparison, relative way, check for matching combinations // comment to disable
                    //if ($hn === $cn || strpos($hn, $cn) !== false || strpos($cn, $hn) !== false) {
                    // strict comparison, safest way // comment to disable
                    if ($hn === $cn) {
                        return $i;
                    }
                }
            }
            return -1;
        };
        
        // detect important columns
        $ext_ref_candidates = array('ID referinta externa', 'ext_ref'); // originals xlsx: "ID referinta externa", mp: "ext_ref" (!) // alternative: array('ID referinta externa','Referinta Externa','External Reference ID','ext_ref')
        $mpid_candidates = array('ID produs', 'id'); // originals xlsx: "ID produs", mp: "id" (!) // alternative: array('ID produs','Product ID', 'id produs', 'product id', 'id', 'product_id')
        $parent_mpid_candidates = array('ID produs (produs de baza)', 'id_base'); // originals xlsx: "ID produs (produs de baza)", mp: n/a // alternative: array('produs (produs de baza)','Product Base ID')
        
        $idx_ext_1 = $findHeaderIndex($header1, $ext_ref_candidates);
        $idx_ext_2 = $findHeaderIndex($header2, $ext_ref_candidates);
        
        // try to find merchant pro product id in simple feed (some feeds include it)
        $idx_mpid_1 = $findHeaderIndex($header1, $mpid_candidates);
        // in variants feed, parent mp id column
        $idx_parent_mpid = $findHeaderIndex($header2, $parent_mpid_candidates);
        
        // build unified header = header1 + missing columns from header2
        $unified = $header1;
        foreach ($header2 as $col) {
            if (!in_array($col, $unified, true)) $unified[] = $col;
        }
        // prepend product_type
        array_unshift($unified, 'product_type');
        
        // Build helper maps for matching:
        // Map simple rows by MerchantPro product id (if found)
        $simple_by_mpid = array();     // mpid => row assoc
        $simple_by_extref = array();   // ext_ref => row assoc
        $simple_rows_assoc = array();   // keep ordered list of assoc rows (in same order as data1)
        
        // Convert simple feed rows to assoc maps using header1
        foreach ($data1 as $r) {
            $assoc = array();
            for ($i = 0; $i < count($header1); $i++) {
                $assoc[$header1[$i]] = isset($r[$i]) ? $r[$i] : '';
            }
            // store ext_ref if available
            $ext_ref_val = ($idx_ext_1 !== -1 && isset($r[$idx_ext_1])) ? trim($r[$idx_ext_1]) : '';
            $mpid_val = ($idx_mpid_1 !== -1 && isset($r[$idx_mpid_1])) ? trim($r[$idx_mpid_1]) : '';
            if ($mpid_val !== '') {
                $simple_by_mpid[$mpid_val] = $assoc;
            }
            if ($ext_ref_val !== '') {
                $simple_by_extref[$ext_ref_val] = $assoc;
            }
            $simple_rows_assoc[] = $assoc;
        }
        
        // Parse variants feed and group by parent mpid (primary method) and also by extref base (fallback)
        $variants_by_parent = array();    // parent_mpid => array of variant assoc rows
        $variants_orphans = array();      // keep those we cannot map by parent mpid for now
        foreach ($data2 as $r) {
            $assoc = array();
            for ($i = 0; $i < count($header2); $i++) {
                $assoc[$header2[$i]] = isset($r[$i]) ? $r[$i] : '';
            }
            $parent_mpid = ($idx_parent_mpid !== -1 && isset($r[$idx_parent_mpid])) ? trim($r[$idx_parent_mpid]) : '';
            $variant_ext = ($idx_ext_2 !== -1 && isset($r[$idx_ext_2])) ? trim($r[$idx_ext_2]) : '';
            
            if ($parent_mpid !== '') {
                if (!isset($variants_by_parent[$parent_mpid])) $variants_by_parent[$parent_mpid] = array();
                $variants_by_parent[$parent_mpid][] = $assoc;
            } else {
                // fallback: try to extract base ext_ref (strip first underscore)
                if ($variant_ext !== '') {
                    $pos = strpos($variant_ext, '_');
                    $base = $pos !== false ? substr($variant_ext, 0, $pos) : $variant_ext;
                    // try matching base against simple's ext_ref
                    if ($base !== '' && isset($simple_by_extref[$base])) {
                        // map to that simple row's mpid if possible (we need the mpid)
                        // try to find mpid for the matched simple row (look into header1 mpid idx)
                        $matched_simple = $simple_by_extref[$base];
                        $mpid_for_matched = '';
                        if ($idx_mpid_1 !== -1 && isset($matched_simple[$header1[$idx_mpid_1]])) {
                            $mpid_for_matched = trim($matched_simple[$header1[$idx_mpid_1]]);
                        }
                        if ($mpid_for_matched !== '') {
                            if (!isset($variants_by_parent[$mpid_for_matched])) $variants_by_parent[$mpid_for_matched] = array();
                            $variants_by_parent[$mpid_for_matched][] = $assoc;
                            continue;
                        }
                    }
                }
                // otherwise treat as orphan for now
                $variants_orphans[] = $assoc;
            }
        }
        
        // Build output rows: iterate through simple rows (preserve order),
        // add the simple row (product_type simple/variable) and then append its variants (if any).
        $output_rows = array();
        
        // We'll need a helper to build numeric row in unified order
        $makeNumericRow = function($assocRow, $unifiedHeader) {
            $out = array();
            foreach ($unifiedHeader as $col) {
                if ($col === 'product_type') {
                    // product_type will be set by the caller in assocRow if present
                    $out[] = isset($assocRow['product_type']) ? $assocRow['product_type'] : '';
                    continue;
                }
                $out[] = isset($assocRow[$col]) ? $assocRow[$col] : '';
            }
            return $out;
        };
        
        // keep track of mpids we processed (so we won't add their variants again)
        $processed_mpids = array();
        
        foreach ($simple_rows_assoc as $s_assoc) {
            // detect mpid and ext_ref
            $mpid_val = ($idx_mpid_1 !== -1 && isset($s_assoc[$header1[$idx_mpid_1]])) ? trim($s_assoc[$header1[$idx_mpid_1]]) : '';
            $extref_val = ($idx_ext_1 !== -1 && isset($s_assoc[$header1[$idx_ext_1]])) ? trim($s_assoc[$header1[$idx_ext_1]]) : '';
            
            // decide if variable (has variants) or simple
            $has_variants = false;
            if ($mpid_val !== '' && isset($variants_by_parent[$mpid_val])) {
                $has_variants = true;
            } else {
                // also check by extref matching: some feeds might not include mpid in simple header
                if ($extref_val !== '' && isset($variants_by_parent[$extref_val])) {
                    $has_variants = true;
                } else {
                    // Also check by checking if any variant's ext_ref base equals this extref_val
                    foreach ($variants_by_parent as $parent => $vrows) {
                        foreach ($vrows as $vr) {
                            $vr_ext = ($idx_ext_2 !== -1 && isset($vr[$header2[$idx_ext_2]])) ? trim($vr[$header2[$idx_ext_2]]) : '';
                            if ($vr_ext !== '') {
                                $pos = strpos($vr_ext, '_');
                                $base = $pos !== false ? substr($vr_ext, 0, $pos) : $vr_ext;
                                if ($base !== '' && $extref_val !== '' && $base === $extref_val) {
                                    $has_variants = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
            
            // set product_type
            $row_assoc = $s_assoc;
            $row_assoc['product_type'] = $has_variants ? 'variable' : 'simple';
            $output_rows[] = $makeNumericRow($row_assoc, $unified);
            
            // append variants for this mpid if any
            if ($mpid_val !== '' && isset($variants_by_parent[$mpid_val])) {
                foreach ($variants_by_parent[$mpid_val] as $v_assoc) {
                    $v_assoc['product_type'] = 'variant';
                    // ensure variant inherits some parent columns when missing (optional)
                    // e.g., if variant doesn't have category column but parent does,
                    // we could copy them. For now we leave as-is (merged header will fill gaps)
                    $output_rows[] = $makeNumericRow($v_assoc, $unified);
                }
                $processed_mpids[$mpid_val] = true;
                // remove them from variants_by_parent so they won't be appended again
                unset($variants_by_parent[$mpid_val]);
            } else {
                // maybe we matched via extref base: try to find matching parent key in variants_by_parent
                if ($extref_val !== '') {
                    // search for variants whose ext_ref base equals extref_val
                    foreach ($variants_by_parent as $parent => $vrows) {
                        $matched_any = false;
                        foreach ($vrows as $k => $v_assoc) {
                            $v_ext = ($idx_ext_2 !== -1 && isset($v_assoc[$header2[$idx_ext_2]])) ? trim($v_assoc[$header2[$idx_ext_2]]) : '';
                            if ($v_ext !== '') {
                                $pos = strpos($v_ext, '_');
                                $base = $pos !== false ? substr($v_ext, 0, $pos) : $v_ext;
                                if ($base === $extref_val) {
                                    // append variant
                                    $v_assoc['product_type'] = 'variant';
                                    $output_rows[] = $makeNumericRow($v_assoc, $unified);
                                    $matched_any = true;
                                    // mark for removal
                                    unset($variants_by_parent[$parent][$k]);
                                }
                            }
                        }
                        // clean empty arrays
                        if ($matched_any && empty($variants_by_parent[$parent])) unset($variants_by_parent[$parent]);
                    }
                }
            }
        }
        
        // After processing simple rows, append any remaining variants (orphans)
        // They were originally in $variants_by_parent or $variants_orphans
        if (!empty($variants_by_parent)) {
            foreach ($variants_by_parent as $parent => $vrows) {
                foreach ($vrows as $v_assoc) {
                    $v_assoc['product_type'] = 'variant';
                    $output_rows[] = $makeNumericRow($v_assoc, $unified);
                }
            }
        }
        if (!empty($variants_orphans)) {
            foreach ($variants_orphans as $v_assoc) {
                $v_assoc['product_type'] = 'variant';
                $output_rows[] = $makeNumericRow($v_assoc, $unified);
            }
        }
        
        // Prepare final sheet array: first row is header ($unified)
        $sheet = array();
        $sheet[] = $unified;
        foreach ($output_rows as $r) $sheet[] = $r;
        
        // Ensure all cells are raw text, $sheet[0] is the header row
        for ($i = 1; $i < count($sheet); $i++) {
            foreach ($sheet[$i] as $j => $cell) {
                if ($cell === null) {
                    $sheet[$i][$j] = '';
                } else {
                    $sheet[$i][$j] = "\0" . (string)$cell;
                }
            }
        }
        
        // Save with SimpleXLSXGen
        $filename = $store_slug . '_mp-export_feed-all-products_' . date('Y-m-d') . '.xlsx';
        $filepath = DIR_LOGS . $filename;
        
        try {
            if (class_exists('Shuchkin\SimpleXLSXGen')) {
                $xls = Shuchkin\SimpleXLSXGen::fromArray($sheet);
                $xls->saveAs($filepath);
            } else {
                return array('success' => false, 'error' => 'SimpleXLSXGen not found.');
            }
        } catch (Exception $e) {
            return array('success' => false, 'error' => 'Failed saving merged XLSX: ' . $e->getMessage());
        }
        
        return array('success' => true, 'filename' => $filename, 'filepath' => $filepath, 'filesimple' => str_replace(DIR_LOGS, '', $local_feed_simple), 'filevariants' => str_replace(DIR_LOGS, '', $local_feed_variants));
    }
    
    // Convert memory_limit to bytes - to allocate more memory as xlsx files are processed (large number of records)
    protected function memoryToBytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return $val;
    }
    
    // Derive store slug from API settings mp_api_url if available
    protected function deriveStoreSlugFromApi($api) {
        $url = '';
        if (!empty($api['mp_api_url'])) $url = $api['mp_api_url'];
        if (!$url) return '';
        return $this->deriveStoreSlugFromUrl($url);
    }
    
    // Derive a short slug from a URL host: strip www., replace '.' with '-' 
    public function deriveStoreSlugFromUrl($url) {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            // try parse as URL without scheme
            if (preg_match('#^([A-Za-z0-9\.\-]+)#', $url, $m)) $host = $m[1];
        }
        if (!$host) return '';
        $host = preg_replace('/^www\./i', '', $host);
        $slug = str_replace('.', '-', $host);
        $slug = preg_replace('/[^A-Za-z0-9\-_]/', '', $slug);
        return strtolower($slug);
    }
    
    // download and save file from URL (used to get the MP xlsx feeds for simple+variable and variants, but also for other downloads if needed)
    protected function downloadFile($url, $dest) {
        $fp = fopen($dest, 'w+');
        if (!$fp) {
            throw new Exception('Unable to open destination file for writing: ' . $dest);
        }

        $ch = curl_init($url);
        if (!$ch) {
            fclose($fp);
            @unlink($dest);
            throw new Exception('Unable to initialize cURL for URL: ' . $url);
        }

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60s timeout
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);

        $execResult = curl_exec($ch);
        $errno      = curl_errno($ch);
        $errstr     = curl_error($ch);
        $status     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $bytesWritten = ftell($fp);

        curl_close($ch);
        fclose($fp);

        if ($errno) {
            @unlink($dest);
            throw new Exception('Curl error: ' . $errstr);
        }

        if ($execResult === false) {
            @unlink($dest);
            throw new Exception('Curl execution failed for URL: ' . $url);
        }

        if ($status < 200 || $status >= 300) {
            @unlink($dest);
            throw new Exception('Unexpected HTTP status ' . $status . ' when downloading ' . $url);
        }

        if ($bytesWritten === 0) {
            @unlink($dest);
            throw new Exception('Downloaded file is empty for URL: ' . $url);
        }
    }
    
    // Get the latest MP xlsx files (consolidated and feeds with paths) or empty string
    public function getLatestXLSXFeedFiles() {
        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSetting('sdx_export_to_mp_sync');
        $api = isset($settings['sdx_export_to_mp_sync_api']) ? $settings['sdx_export_to_mp_sync_api'] : array();
        
        $store_slug = $this->deriveStoreSlugFromApi($api);
        if (!$store_slug) return '';
        
        $pattern = DIR_LOGS . $store_slug . '_mp-export_*.xlsx';
        $filepaths = glob($pattern);
        if (!$filepaths) return '';
        
        // pick newest by modified time
        //usort($filepaths, function($a, $b) {
        //    return filemtime($b) - filemtime($a);
        //});
        
        //return basename($files[0]);
        return $filepaths;
    }
    
    /* === end of get and update MerchantPro Consolidated Feed === */
    
    /**
     * Returns array of OC products, filtered and paginated if selected
     * Each OC product is an assoc array with keys:
     *  - product_type: 'simple'|'variable'|'variant'
     *  - product_id_base: integer id of OC product - original - used also to indicate the base id of variant product types
     *  - product_id: string id determined by OC product_type and used in MP for matching (e.g. "9975" or "9975_Alb-Cald")
     *  - ext_ref: string id (same as product_id to be used for matching MP products)
     *  - model, model_base, name, quantity, price, price_special: used to check the sync with MP
     *  - status, categories_list: by filtering
     *  - image, sku, sku_base, mpn, stock_status_id, categories_paths, options_display (compact): informative, used on listing
     */
    public function getProductsForMP($filter = array()) {
        
        $base_products = $this->getProducts($filter);
        
        $out = array();
        
        foreach ($base_products as $bp) {
            $product_id = (int)$bp['product_id'];
            $image = !empty($bp['image']) ? $bp['image'] : '';
            $name = isset($bp['name']) ? $bp['name'] : '';
            $model = !empty($bp['model']) ? $bp['model'] : $product_id; // maybe empty?
            $sku = !empty($bp['sku']) ? $bp['sku'] : '';
            $mpn = !empty($bp['mpn']) ? $bp['mpn'] : '';
            $status = !empty($bp['status']) ? $bp['status'] : 0;
            $quantity = !empty($bp['quantity']) ? (int)$bp['quantity'] : 0;
            $stock_status_id = isset($bp['stock_status_id']) ? (int)$bp['stock_status_id'] : 0;
            $price = isset($bp['price']) ? (float)$bp['price'] : '';
            $lowest_special = $this->getProductLowestSpecial($product_id);
            
            // categories (paths + plain list)
            $cats_paths = $this->getProductCategoriesPaths($product_id);
            $cats_plain = $this->getProductCategoriesPlain($product_id);
            $cats_list_numbered = array();
            $i = 1;
            foreach ($cats_plain as $cn) { $cats_list_numbered[] = $i . '. ' . $cn; $i++; }
            $cats_list_text = implode("\n", $cats_list_numbered);
            
            // Get select-type options (only those with type 'select')
            $select_options = $this->getProductSelectOptions($product_id);
            
            // simple / basic product row (no select-type options)
            if (empty($select_options)) {
                $row = array(
                    'product_type'      => 'simple',
                    'product_id'        => $product_id,
                    'product_id_base'   => $product_id,
                    'ext_ref'           => $product_id,
                    'image'             => $image,
                    'name'              => $name,
                    'model'             => $model,
                    'model_base'        => $model,
                    'sku'               => $sku,
                    'sku_base'          => $sku,
                    'mpn'               => $mpn,
                    'mpn_base'          => $mpn,
                    'status'            => $status,
                    'quantity'          => $quantity,
                    'stock_status_id'   => $stock_status_id,
                    'price'             => $price,
                    'lowest_special'    => $lowest_special,
                    'categories_paths'  => $cats_paths,
                    'categories_list'   => $cats_list_text,
                    'options_display'   => array()
                );
                $out[] = $row;
                
            } else {
                // variable product (parent) row (select-type options)
                $row_parent = array(
                    'product_type'      => 'variable',
                    'product_id'        => $product_id,
                    'product_id_base'   => $product_id,
                    'ext_ref'           => $product_id,
                    'image'             => $image,
                    'name'              => $name,
                    'model'             => $model,
                    'model_base'        => $model,
                    'sku'               => $sku,
                    'sku_base'          => $sku,
                    'mpn'               => $mpn,
                    'mpn_base'          => $mpn,
                    'status'            => $status,
                    'quantity'          => $quantity,
                    'stock_status_id'   => $stock_status_id,
                    'price'             => $price,
                    'lowest_special'    => $lowest_special,
                    'categories_paths'  => $cats_paths,
                    'categories_list'   => $cats_list_text,
                    'options_display'   => $select_options
                );
                $out[] = $row_parent;
                
                // variant product row (values of select-type option)
                foreach ($select_options as $select_option) {
                    $named = $select_option['name'];
                    foreach($select_option['options'] as $option) {
                        //..
                        $suffix = $this->slugifyForExtRef($option['option']);
                        $quantity = $option['quantity']; // variant stock quantity, comment to leave the parent quantity
                        if($option['price_prefix'] == '+') {
                            $price = $price + $option['price']; // variant price +, comment to leave the parent price
                        }
                        if($option['price_prefix'] == '-') {
                            $price = $price - $option['price']; // variant price -, comment to leave the parent price
                        }
                        
                        $v_row = array(
                            'product_type'      => 'variant',
                            'product_id'        => $product_id . '_' . $suffix,
                            'product_id_base'   => $product_id,
                            'ext_ref'           => $product_id . '_' . $suffix,
                            'image'             => '', // no thumb for variants
                            'name'              => $name,
                            'model'             => $model . '_' . $suffix,
                            'model_base'        => $model,
                            'sku'               => !empty($sku) ? $sku . '_' . $suffix : '',
                            'sku_base'          => $sku,
                            'mpn'               => !empty($mpn) ? $mpn . '_' . $suffix : '',
                            'mpn_base'          => $mpn,
                            'status'            => $status,
                            'quantity'          => $quantity, // check for variant stock quantity 
                            'stock_status_id'   => $stock_status_id,
                            'price'             => $price,
                            'lowest_special'    => $lowest_special,
                            'categories_paths'  => $cats_paths,
                            'categories_list'   => $cats_list_text,
                            'options_display'   => array('name' => $named, 'option' => $option['option']) // array of option values for this variant
                        );
                        $out[] = $v_row;
                    }
                }
            }
        }
        
        return $out;
    }
    
    // get the lowest special price of OC product - to check the sync with MP
    protected function getProductLowestSpecial($product_id) {
        $lowest_special = null;
        $special_rows = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$product_id . "' ORDER BY priority, price")->rows;
        foreach ($special_rows as $sp) {
            if (isset($sp['price'])) {
                $p = (float)$sp['price'];
                if ($lowest_special === null || $p < $lowest_special) {
                    $lowest_special = $p;
                }
            }
        }
        return $lowest_special;
    }
    
    // helper: get OC product categories paths (array of "Parent > Child") by OC product_id
    protected function getProductCategoriesPaths($product_id) {
        
        $ids = array();
        $query = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");
        foreach ($query->rows as $r) $ids[] = $r['category_id'];
        
        $out = array();
        foreach ($ids as $cid) {
            $path = $this->getCategoryPath($cid);
            if ($path) $out[] = $path;
        }
        return $out;
        
    }
    
    // helper: get OC category path (Parent > Child) by OC category_id
    protected function getCategoryPath($category_id) {
        $path = array();
        while ($category_id) {
            $q = $this->db->query("SELECT parent_id FROM " . DB_PREFIX . "category WHERE category_id = '" . (int)$category_id . "'");
            if (!$q->num_rows) break;
            $parent_id = (int)$q->row['parent_id'];
            $qd = $this->db->query("SELECT name FROM " . DB_PREFIX . "category_description WHERE category_id = '" . (int)$category_id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");
            $name = $qd->num_rows ? $qd->row['name'] : '';
            array_unshift($path, $name);
            $category_id = $parent_id;
        }
        return implode(' > ', array_filter($path));
    }
    
    // product OC categories plain (no path)
    protected function getProductCategoriesPlain($product_id) {
        
        $sql = "SELECT cd.name FROM " . DB_PREFIX . "product_to_category pc
                JOIN " . DB_PREFIX . "category_description cd ON (pc.category_id = cd.category_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "')
                WHERE pc.product_id = '" . (int)$product_id . "'
                ORDER BY cd.name ASC";
        
        $query = $this->db->query($sql);
        $out = array();
        foreach ($query->rows as $r) $out[] = $r['name'];
        return $out;
        
    }
    
    // returns array of select-type options for product
    protected function getProductSelectOptions($product_id) {
        $language_id = (int)$this->config->get('config_language_id');
        
        $sql = "SELECT po.product_option_id, po.option_id, od.name AS option_name, o.type
                    FROM " . DB_PREFIX . "product_option po
                        JOIN `" . DB_PREFIX . "option` o ON (po.option_id = o.option_id)
                        JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id AND od.language_id = '" . $language_id . "')
                    WHERE po.product_id = '" . (int)$product_id . "'";
        $query = $this->db->query($sql);
        
        $out = array();
        
        foreach ($query->rows as $prow) {
            // only select type options
            if (!isset($prow['type']) || strtolower($prow['type']) !== 'select') continue;
            
            $product_option_id = (int)$prow['product_option_id'];
            $opt_name = $prow['option_name'];
            
            // get product_option_values (values for this product_option)
            $sql2 = "SELECT pov.*, ovd.name AS value_name
                     FROM " . DB_PREFIX . "product_option_value pov
                     LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pov.option_value_id = ovd.option_value_id AND ovd.language_id = '" . $language_id . "')
                     WHERE pov.product_option_id = '" . $product_option_id . "'
                     ORDER BY pov.product_option_value_id ASC";
            $q2 = $this->db->query($sql2);
            
            $values = array();
            $options = array();
            foreach ($q2->rows as $v) {
                $values[] = $v['value_name'];
                $options[] = array('option' => $v['value_name'], 'quantity' => $v['quantity'], 'price' => $v['price'], 'price_prefix' => $v['price_prefix'], 'weight' => $v['weight'], 'weight_prefix' => $v['weight_prefix']);
            }
            
            if (!empty($values)) {
                $out[] = array(
                    'option_id' => (int)$prow['option_id'],
                    'name' => $opt_name,
                    'values' => $values,
                    'options' => $options
                );
            }
        }
        
        return $out;
    }
    
    // builds a safe slug (ASCII, no spaces, dash separated, optionally lower-case) for variant suffixes 
    // example: "Alb Cald" -> "Alb-Cald" * Note: original case style is kept; for safety, diacritics are removed and spaces are replaced with '-'
    protected function slugifyForExtRef($text) {
        $text = trim((string)$text);
        if ($text === '') return '';
        
        // Transliterate diacritics (if iconv available)
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($t !== false) $text = $t;
        }
        
        // Replace spaces and slashes with dash
        $text = preg_replace('/[\/\s]+/', '-', $text);
        
        // Remove characters that are not alnum, dash or underscore
        $text = preg_replace('/[^A-Za-z0-9\-_]/', '', $text);
        
        // Optionally, convert to lower-case; MerchantPro examples used mixed-case; keep original-ish
        // $text = strtolower($text);
        
        // Collapse multiple dashes
        $text = preg_replace('/-+/', '-', $text);
        
        return $text;
    }
    
    // get/create the cache of consolidated MP feed for products // it reads the XLSX file (consolidated)
    protected function getConsolidatedMPcache($force = false) {
        
        $current_limit = ini_get('memory_limit');
        $desired_limit = 1 * 1024 * 1024 * 1024; // 1GB, 1.5GB, 2GB in bytes
        if ($this->memoryToBytes($current_limit) < $desired_limit) {
            ini_set('memory_limit', $desired_limit); // Increase to $desired_limit (1GB, 1.5GB, 2GB, ...)
        }
        set_time_limit(60);                // // Prevent timeout but keep safety net (1 minutes)
        //set_time_limit(600);                // // Prevent timeout but keep safety net (10 minutes)
        //ini_set('memory_limit', '1024M'); // or '2G' for really big exports
        //set_time_limit(0);                // prevent timeout
        
        // load settings & derive store slug
        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSetting('sdx_export_to_mp_sync');
        $api = isset($settings['sdx_export_to_mp_sync_api']) ? $settings['sdx_export_to_mp_sync_api'] : array();
        $store_slug = $this->deriveStoreSlugFromApi($api);
        if (!$store_slug) {
            return array('success' => false, 'error' => 'store_slug_not_found');
        }
        
        // find latest consolidated XLSX file (pattern)
        $pattern = DIR_LOGS . $store_slug . '_mp-export_feed-all-products_*.xlsx';
        $files = glob($pattern);
        if (!$files) {
            return array('success' => false, 'error' => 'no_consolidated_feed');
        }
        usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
        $xlsxfile = $files[0];
        $xlsx_mtime = filemtime($xlsxfile);
        
        // cache path -> find latest json cache file (pattern)
        $cachepattern = DIR_LOGS . $store_slug . '_mp-export_all-products-cache_*.json';
        $cachefiles = glob($cachepattern);
        if ($cachefiles) {
            usort($cachefiles, function($cfa, $cfb) { return filemtime($cfb) - filemtime($cfa); });
            $cachefile = $cachefiles[0];
        } else {
            $cachefile = DIR_LOGS . $store_slug . '_mp-export_all-products-cache_' . date('Y-m-d') . '.json';
        }
        
        // if cache exists and fresh -> return it, else force rebuild
        if (!$force && is_file($cachefile) && filemtime($cachefile) >= $xlsx_mtime) {
            $json = file_get_contents($cachefile);
            $data = json_decode($json, true);
            if (is_array($data) && isset($data['map'])) {
                return array('success' => true, 'cachefile' => $cachefile, 'map' => $data['map'], 'mp_extra'  => isset($data['mp_extra']) ? $data['mp_extra'] : array(), 'meta' => isset($data['meta']) ? $data['meta'] : array());
            }
        }
        
        // Parse XLSX and build map
        if (!class_exists('Shuchkin\SimpleXLSX')) {
            require_once(DIR_SYSTEM . '/library/SimpleXLSX/SimpleXLSX.php');
        }
        
        try {
            $xlsx = Shuchkin\SimpleXLSX::parse($xlsxfile);
            if (!$xlsx) {
                $err = method_exists('Shuchkin\SimpleXLSX','parseError') ? Shuchkin\SimpleXLSX::parseError() : 'parse_failed';
                return array('success'=>false,'error'=>'parse_error: '.$err);
            }
        } catch (Exception $e) {
            return array('success'=>false,'error'=>'exception_parse: '.$e->getMessage());
        }
        
        $rows = $xlsx->rows();
        if (empty($rows) || count($rows) < 1) {
            return array('success' => false, 'error' => 'empty_feed');
        }
        
        $header = array_map('trim', $rows[0]);
        $data_rows = array_slice($rows, 1);
        
        // helpers
        $normalize = function($s) {
            $s = trim((string)$s);
            //if ($s === '') return '';
            if (function_exists('iconv')) {
                $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
                if ($t) $s = $t;
                //if ($t !== false) $s = $t;
            }
            $s = mb_strtolower($s, 'UTF-8');
            // remove punctuation except underscore and dash, collapse whitespace
            $s = preg_replace('/[^\p{L}\p{N}\s\_\-]/u', ' ', $s);
            // keep only one space for string comparison
            $s = preg_replace('/\s+/', ' ', $s);
            // remove space for consistent string comparison // comment it to disable
            $s = preg_replace('/\s+/', '', $s);
            // trim at the end of string
            ///$s = trim($s, " \t\n\r\0\x0B\"'`");
            $s = trim($s, " \t\n\r\0\x0B\xC2\xA0\"'`");
            return $s;
        };
        
        $findHeaderIndex = function($headerArray, $candidates) use ($normalize) {
            foreach ($headerArray as $i => $col) {
                $hn = $normalize($col);
                foreach ($candidates as $cand) {
                    $cn = $normalize($cand);
                    // extended comparison, relative way, check for matching combinations // comment to disable
                    //if ($hn === $cn || strpos($hn, $cn) !== false || strpos($cn, $hn) !== false) {
                    // strict comparison, safest way // comment to disable
                    if ($hn === $cn) {
                        return $i;
                    }
                }
            }
            return -1;
        };
        
        // candidate lists - determine header
        $mp_type_candidates = array('product_type', 'type'); // originals xlsx: "product_type", mp: "type" (! - 'basic','multi-variant') // alternative: array('product_type','Product Type')
        $mp_id_candidates = array('ID produs', 'id'); // originals xlsx: "ID produs", mp: "id" (!) // alternative: array('ID produs','Product ID')
        $mp_id_base_candidates = array('ID produs (produs de baza)', 'id_base'); // originals xlsx: "ID produs (produs de baza)", mp: n/a // alternative: array('produs (produs de baza)','Product Base ID')
        $ext_ref_candidates = array('ID referinta externa', 'ext_ref'); // originals xlsx: "ID referinta externa", mp: "ext_ref" (!) // alternative: array('ID referinta externa','Referinta Externa','External Reference ID','ext_ref')
        $sku_candidates = array('Cod produs - SKU', 'sku'); // originals xlsx: "Cod produs - SKU", mp: "sku" (!) // alternative: array('Cod produs - SKU','Product Code SKU')
        $sku_base_candidates = array('Cod produs - SKU (produs de baza)', 'sku_base'); // originals xlsx: "Cod produs - SKU (produs de baza)", mp: n/a // alternative: array('Cod produs - SKU (produs de baza)','Product Base SKU','sku_base')
        $price_candidates = array('Pret produs', 'price_gross'); // originals xlsx: "Pret produs", mp: "price_gross" (!) // alternative: array('Pret produs','Product Price')
        $price_old_candidates = array('Pret vechi', 'old_price_gross'); // originals xlsx: "Pret vechi", mp: "old_price_gross" (!) // alternative: array('Pret vechi','Old Price')
        $stock_candidates = array('Stoc', 'stock'); // originals xlsx: "Stoc", mp: "stock" (!) // alternative: array('Stoc','Stock')
        $name_candidates = array('Nume produs', 'name'); // originals xlsx: "Nume produs", mp: "name" (!) // alternative: array('Nume produs','Product Name')
        
        $idx_type = $findHeaderIndex($header, $mp_type_candidates);
        $idx_id = $findHeaderIndex($header, $mp_id_candidates);
        
        $idx_ext = $findHeaderIndex($header, $ext_ref_candidates);
        $idx_sku = $findHeaderIndex($header, $sku_candidates);
        $idx_sku_base = $findHeaderIndex($header, $sku_base_candidates);
        $idx_price = $findHeaderIndex($header, $price_candidates);
        $idx_price_old = $findHeaderIndex($header, $price_old_candidates);
        $idx_stock = $findHeaderIndex($header, $stock_candidates);
        $idx_name = $findHeaderIndex($header, $name_candidates);
        
        // parse numbers helper
        $parseNumber = function($val) {
            if ($val === null || $val === '') return null;
            $s = (string)$val;
            // strip everything except digits . , -
            $s = preg_replace('/[^\d\.,\-]/', '', $s);
            // normalize decimal separator
            if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
                $s = str_replace(',', '', $s); // "1,234.56" -> "1234.56"
            } elseif (strpos($s, ',') !== false) {
                $s = str_replace(',', '.', $s); // "123,45" -> "123.45"
            }
            $s = trim($s);
            if ($s === '' || $s === '-') return null;
            return round((float)$s, 2); // numeric float
        };
        
        $map = array();
        $mp_extra = array();
        
        foreach ($data_rows as $row) {
            // build minimal row
            $mp_id = ($idx_id !== -1 && isset($row[$idx_id])) ? trim((string)$row[$idx_id]) : '';
            $mp_type = ($idx_type !== -1 && isset($row[$idx_type])) ? trim((string)$row[$idx_type]) : '';
            
            $ext_val = ($idx_ext !== -1 && isset($row[$idx_ext])) ? trim((string)$row[$idx_ext]) : '';
            
            // compute ext_ref_base safely (for variants only)
            $ext_base = $ext_val;
            $pos = strpos($ext_val, '_');
            if ($pos === true) $ext_base = substr($ext_val, 0, $pos);
            
            $mp_sku = ($idx_sku !== -1 && isset($row[$idx_sku])) ? trim((string)$row[$idx_sku]) : ''; // if $row[$idx_sku] is not determined, maybe put null to mark error as sku is mandatory
            $mp_sku_base = ($idx_sku_base !== -1 && isset($row[$idx_sku_base])) ? trim((string)$row[$idx_sku_base]) : ''; // if $row[$idx_sku_base] is not determined, maybe put null to mark missing mp_sku_base (as in consolidated xlsx file)
            
            $mp_price = ($idx_price !== -1 && isset($row[$idx_price])) ? $parseNumber($row[$idx_price]) : null;
            $mp_price_old = ($idx_price_old !== -1 && isset($row[$idx_price_old])) ? $parseNumber($row[$idx_price_old]) : null;
            $mp_stock = ($idx_stock !== -1 && isset($row[$idx_stock])) ? (is_numeric($row[$idx_stock]) ? (int)$row[$idx_stock] : null) : null;
            $mp_name = ($idx_name !== -1 && isset($row[$idx_name])) ? trim((string)$row[$idx_name]) : ''; // if $row[$idx_name] is not determined, maybe put null to mark error as name is mandatory
            
            // collect mp products with missing ext_ref (added manually or left-overs in mp - potentially out-of-sync)
            if ($ext_val === '') {
                $mp_extra[$mp_sku] = array(
                    'mp_id'         => $mp_id,
                    'mp_type'       => $mp_type,
                    'mp_ext_ref'    => $ext_val, // maybe null?
                    'ext_ref_base'  => $ext_base, // maybe null?
                    'mp_sku'        => $mp_sku,
                    'mp_sku_base'   => $mp_sku_base,
                    'mp_price'      => $mp_price,
                    'mp_price_old'  => $mp_price_old,
                    'mp_stock'      => $mp_stock,
                    'mp_name'       => $mp_name
                );
            }
            // collect mp products with known ext_ref
            else {
                $map[$ext_val] = array(
                    'mp_id'         => $mp_id,
                    'mp_type'       => $mp_type,
                    'mp_ext_ref'    => $ext_val,
                    'ext_ref_base'  => $ext_base,
                    'mp_sku'        => $mp_sku,
                    'mp_sku_base'   => $mp_sku_base,
                    'mp_price'      => $mp_price,
                    'mp_price_old'  => $mp_price_old,
                    'mp_stock'      => $mp_stock,
                    'mp_name'       => $mp_name
                );
            }
            
        }
        
        // assemble cache and save
        $cache = array(
            'meta'      => array('file' => $xlsxfile, 'generated' => $xlsx_mtime, 'store_slug' => $store_slug),
            'totalmap'     => count($map),
            'totalextra'     => count($mp_extra),
            'map'       => $map,
            'mp_extra'  => $mp_extra
        );
        
        // delete any existing $cachepattern json files
        $dfiles = glob($cachepattern);
        if ($dfiles) { foreach ($dfiles as $df) { @unlink($df); } }
        $cachefile = DIR_LOGS . $store_slug . '_mp-export_all-products-cache_' . date('Y-m-d') . '.json';
        // write $cachefile file atomically
        $tmp = $cachefile . '.tmp';
        file_put_contents($tmp, json_encode($cache, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX);
        @rename($tmp, $cachefile);
        
        return array('success' => true, 'cachefile' => $cachefile, 'map' => $map, 'mp_extra'  => $mp_extra, 'meta' => $cache['meta']);
    }
    
    // check OC products against MP feed products // compare prices, stock, model vs sku, model_base vs sku_base, names
    public function checkOCagainstMP($filter = array(), $force_rebuild_cache = false) {
        
        // initialize the output
        $out = array();
        
        // Load helper models
        $this->load->model('catalog/product');
        $this->load->model('localisation/stock_status');
        
        // normalization helper
        $normalizeStr = function($s) {
            $s = trim((string)$s);
            if ($s === '') return '';
            if (function_exists('iconv')) {
                $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
                if ($t !== false) $s = $t;
            }
            $s = mb_strtolower($s, 'UTF-8');
            $s = preg_replace('/\s+/', ' ', $s);
            // remove space to tighten the comparison...
            //$s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $s);
            $s = trim($s);
            return $s;
        };
        // parse number helper
        $parseNumber = function($val) {
            if ($val === null || $val === '') return null;
            $s = (string)$val;
            // strip everything except digits . , -
            $s = preg_replace('/[^\d\.,\-]/', '', $s);
            // normalize decimal separator
            if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
                $s = str_replace(',', '', $s); // "1,234.56" -> "1234.56"
            } elseif (strpos($s, ',') !== false) {
                $s = str_replace(',', '.', $s); // "123,45" -> "123.45"
            }
            $s = trim($s);
            if ($s === '' || $s === '-') return null;
            return round((float)$s, 2); // numeric float
        };
        
        // get OC products
        $oc_products = $this->getProductsForMP($filter);
        // return empty array if no OC products
        if(empty($oc_products)) {
            return array('success' => false, 'error' => 'Error loading OC products', 'oc' => $out);
        }
        
        // get MP products from cache or build that cache
        $mp_cache = $this->getConsolidatedMPcache($force_rebuild_cache);
        // no mp feed
        if (empty($mp_cache['success'])) {
            // no feed -> mark all as 'no_feed'
            foreach ($oc_products as $oc_product) {
                
                $oc_ext_ref = $oc_product['ext_ref'];
                $oc_product_id = (int)$oc_product['product_id_base'];
                
                $pslug = $this->db->query("SELECT `keyword` FROM `" . DB_PREFIX . "url_alias` WHERE query = 'product_id=" . $oc_product_id . "' ")->row;
                $pkeyword = isset($pslug['keyword']) ? $pslug['keyword'] : '';
                $oc_product['product_slug'] = ($pkeyword ? $pkeyword : ''); // maybe needs adjustment by option-determined-slug
                $oc_product['view_product'] = ($pkeyword ? HTTPS_CATALOG.$pkeyword : $this->url->link('product/product', 'product_id=' . $oc_product_id, 'SSL'));
                $oc_product['edit_product'] = $this->url->link('catalog/product/update', 'token=' . $this->session->data['token'] . '&product_id=' . $oc_product_id, 'SSL');
                
                // Stock status text
                $stock_status_text = '';
                if ($oc_product['stock_status_id']) {
                    $ss = $this->model_localisation_stock_status->getStockStatus( (int)$oc_product['stock_status_id'] );
                    if ($ss && isset($ss['name'])) $stock_status_text = $ss['name'];
                }
                $oc_product['stock_status_text'] = $stock_status_text;
                
                $oc_product['specials'] = $this->model_catalog_product->getProductSpecials($oc_product_id);
                
                $oc_product['mp_id'] = null;
                
                $oc_product['mp_sync_status_code'] = 'no_feed'; // available $mp_sync_status_code: no_feed, missing, in_mp, in_mp_by_sku, collision, out_of_sync, price_stock_diff
                $oc_product['mp_sync_status'] = $this->language->get('mp_status_no_feed');
                $oc_product['mp_sync_issues'] = isset($mp_cache['error']) ? $mp_cache['error'] : $this->language->get('mp_status_no_feed');
                $oc_product['mp_matched_by'] = 'none';
                
                $out[$oc_ext_ref] = $oc_product;
            }
            return array('success' => false, 'error' => (isset($mp_cache['error']) ? $mp_cache['error'].' -> '.$this->language->get('error_mp_feed_update') : $this->language->get('mp_status_no_feed')), 'oc' => $out);
        }
        // mp feed with known ext_ref, array of mp products with ext_ref key
        $mp_products = isset($mp_cache['map']) ? $mp_cache['map'] : array();
        
        foreach ($oc_products as $ext => $oc_product) {
            
            $oc_ext_ref = $oc_product['ext_ref'];
            $oc_product_id = (int)$oc_product['product_id_base'];
            
            $pslug = $this->db->query("SELECT `keyword` FROM `" . DB_PREFIX . "url_alias` WHERE query = 'product_id=" . $oc_product_id . "' ")->row;
            $pkeyword = isset($pslug['keyword']) ? $pslug['keyword'] : '';
            $oc_product['product_slug'] = ($pkeyword ? $pkeyword : '');
            $oc_product['view_product'] = ($pkeyword ? HTTPS_CATALOG.$pkeyword : $this->url->link('product/product', 'product_id=' . $oc_product_id, 'SSL'));
            $oc_product['edit_product'] = $this->url->link('catalog/product/update', 'token=' . $this->session->data['token'] . '&product_id=' . $oc_product_id, 'SSL');
            
            // Stock status text
            $stock_status_text = '';
            if ($oc_product['stock_status_id']) {
                $ss = $this->model_localisation_stock_status->getStockStatus( (int)$oc_product['stock_status_id'] );
                if ($ss && isset($ss['name'])) $stock_status_text = $ss['name'];
            }
            $oc_product['stock_status_text'] = $stock_status_text;
            
            $oc_product['specials'] = $this->model_catalog_product->getProductSpecials($oc_product_id);
            
            // initialize the checking variables...
            $mp_product = null;
            $mp_sync_status_code = 'missing'; // available $mp_sync_status_code: no_feed, missing, in_mp, price_stock_diff, out_of_sync
            $mp_sync_status = $this->language->get('mp_status_missing');
            $mp_sync_issues = '';
            $mp_matched_by = 'none';
            
            //checking oc ext_ref against mp ext_ref
            if( isset($mp_products[$oc_ext_ref]) ) {
                // get mp product data
                $mp_product = $mp_products[$oc_ext_ref];
                // main sync states
                $mp_sync_status_code = 'in_mp';
                $mp_sync_status = $this->language->get('mp_status_in_mp');
                $mp_matched_by = 'oc_ext_ref';
                
                // Effective OC price: lowest of special vs regular
                $oc_effective = null;
                if (!empty($oc_product['lowest_special'])) {
                    $oc_effective = $oc_product['lowest_special'];
                } elseif (!empty($oc_product['price'])) {
                    $oc_effective = $oc_product['price'];
                }
                // Effective MP price: lowest of current vs old
                $mp_effective = null;
                if (isset($mp_product['mp_price']) && $mp_product['mp_price'] !== null) {
                    $mp_effective = (float)$mp_product['mp_price'];
                } elseif (isset($mp_product['mp_price_old']) && $mp_product['mp_price_old'] !== null) {
                    $mp_effective = (float)$mp_product['mp_price_old'];
                }
                // Price compare: OC effective vs MP effective 
                if ($oc_effective !== null && $mp_effective !== null && abs($mp_effective - $oc_effective) > 0.01) {
                    $mp_sync_status_code = 'price_stock_diff';
                    $mp_sync_status = $this->language->get('mp_status_price_stock_diff');
                    $mp_sync_issues .= 'mismatch oc_price '.$oc_effective.' vs. mp_price '.$mp_effective.' <br>';
                }
                // Stock
                if ($oc_product['quantity'] !== null && isset($mp_product['mp_stock']) && $mp_product['mp_stock'] !== null) {
                    if ((int)$mp_product['mp_stock'] !== (int)$oc_product['quantity']) {
                        $mp_sync_status_code = 'price_stock_diff';
                        $mp_sync_status = $this->language->get('mp_status_price_stock_diff');
                        $mp_sync_issues .= 'mismatch oc_stock '.$oc_product['quantity'].' vs. mp_stock '.$mp_product['mp_stock'].' <br>';
                    }
                }
                
                // Compare Model vs. SKU
                if (isset($mp_product['mp_sku'])) {
                    if ($mp_product['mp_sku'] !== '' && $normalizeStr($oc_product['model']) !== $normalizeStr($mp_product['mp_sku'])) {
                        $mp_sync_status_code = 'out_of_sync';
                        $mp_sync_status = $this->language->get('mp_status_out_of_sync');
                        $mp_sync_issues .= 'mp_sku_mismatch: '.$mp_product['mp_sku'].' <br>';
                    }
                }
                elseif(empty($mp_product['mp_sku'])){
                    $mp_sync_status_code = 'out_of_sync';
                    $mp_sync_status = $this->language->get('mp_status_out_of_sync');
                    $mp_sync_issues .= 'mp_sku_missing: update needed <br>';
                }
                
                // Compare Model_Base vs. SKU_base
                if (isset($mp_product['mp_sku_base'])) {
                    if ($mp_product['mp_sku_base'] !== '' && $normalizeStr($oc_product['model_base']) !== $normalizeStr($mp_product['mp_sku_base'])) {
                        $mp_sync_status_code = 'out_of_sync';
                        $mp_sync_status = $this->language->get('mp_status_out_of_sync');
                        $mp_sync_issues .= 'mp_sku_base_mismatch: '.$mp_product['mp_sku_base'].' <br>';
                    }
                }
                
                // Compare name (basic normalization)
                if (isset($mp_product['mp_name'])) {
                    if ($normalizeStr($oc_product['name']) !== $normalizeStr($mp_product['mp_name'])) {
                        $mp_sync_status_code = 'out_of_sync';
                        $mp_sync_status = $this->language->get('mp_status_out_of_sync');
                        $mp_sync_issues .= 'mp_name_mismatch: '.$mp_product['mp_name'].' <br>';
                    }
                }
                
                $oc_product['mp_id'] = $mp_product['mp_id'];
                
                $oc_product['mp_sync_status_code'] = $mp_sync_status_code;
                $oc_product['mp_sync_status'] = $mp_sync_status;
                $oc_product['mp_sync_issues'] = $mp_sync_issues;
                $oc_product['mp_matched_by'] = $mp_matched_by;
                
                $out[$oc_ext_ref] = $oc_product;
                
            }
            else {
                // oc products not found/matched within mp products - to be exported to mp via API POST
                
                $oc_product['mp_id'] = null;
                
                $oc_product['mp_sync_status_code'] = 'missing';
                $oc_product['mp_sync_status'] = $this->language->get('mp_status_missing');
                $oc_product['mp_sync_issues'] = 'not_in_mp_feed';
                $oc_product['mp_matched_by'] = 'none';
                
                $out[$oc_ext_ref] = $oc_product;
            }
            
        }
        
        // create json files for mp imports via API * Keep the same structure keyed by ext_ref
        $patch  = array();
        $post   = array();
        foreach ($out as $ext_ref => $entry) {
            if ($entry['mp_sync_status_code'] === 'out_of_sync' || $entry['mp_sync_status_code'] === 'price_stock_diff') {
                $patch[$ext_ref] = $entry;
            } elseif ($entry['mp_sync_status_code'] === 'missing') {
                $post[$ext_ref] = $entry;
            }
        }
        
        // array of mp products without ext_ref key - delete candidates
        $delete = isset($mp_cache['mp_extra']) ? $mp_cache['mp_extra'] : array();
        
        // get the store_slug from mp cache
        isset($mp_cache['meta']['store_slug']) ? $store_slug = $mp_cache['meta']['store_slug'] : $store_slug = '';
        // alternate way to get store_slug if mp cache not available
        if($store_slug == '') {
            // load settings & derive store slug
            $this->load->model('setting/setting');
            $settings = $this->model_setting_setting->getSetting('sdx_export_to_mp_sync');
            $api = isset($settings['sdx_export_to_mp_sync_api']) ? $settings['sdx_export_to_mp_sync_api'] : array();
            $store_slug = $this->deriveStoreSlugFromApi($api);
            if (!$store_slug) {
                return array('success' => false, 'error' => 'store_slug_not_found', 'oc' => $out); // return array with $out but notify about store-slug error
            }
        }
        
        // delete any existing mp-import_products- json files for mp import
        //$mppattern = DIR_LOGS . $store_slug . '_mp-import_products-*.*';
        $mppattern = DIR_LOGS . $store_slug . '_mp-import_products-*.json';
        $mpfiles = glob($mppattern);
        if ($mpfiles) {
            foreach ($mpfiles as $mpf) {
                @unlink($mpf);
            }
        }
        // delete any existing _oc-export_preselected-products_ json files
        $ocpattern = DIR_LOGS . $store_slug . '_oc-export_preselected-products_*.json';
        $ocfiles = glob($ocpattern);
        if ($ocfiles) {
            foreach ($ocfiles as $ocf) {
                @unlink($ocf);
            }
        }
        
        $patchfile      = DIR_LOGS . $store_slug . '_mp-import_products-patch_' . date('Y-m-d') . '.json';
        $postfile       = DIR_LOGS . $store_slug . '_mp-import_products-post_' . date('Y-m-d') . '.json';
        $deletefile     = DIR_LOGS . $store_slug . '_mp-import_products-delete_' . date('Y-m-d') . '.json';
        
        $ocproductsfile = DIR_LOGS . $store_slug . '_oc-export_preselected-products_' . date('Y-m-d') . '.json';
        
        // assemble json patch and save
        $patchcache = array(
            'meta'      => array('file' => $patchfile, 'generated' => time(), 'store_slug' => $store_slug),
            'patch'       => $patch
        );
        // write atomically
        $tmp = $patchfile . '.tmp';
        file_put_contents($tmp, json_encode($patchcache, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX);
        @rename($tmp, $patchfile);
        
        // assemble json post and save
        $postcache = array(
            'meta'      => array('file' => $postfile, 'generated' => time(), 'store_slug' => $store_slug),
            'post'      => $post
        );
        // write atomically
        $tmp = $postfile . '.tmp';
        file_put_contents($tmp, json_encode($postcache, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX);
        @rename($tmp, $postfile);
        
        // assemble json delete and save
        $deletecache = array(
            'meta'      => array('file' => $deletefile, 'generated' => time(), 'store_slug' => $store_slug),
            'delete'    => $delete
        );
        // write atomically
        $tmp = $deletefile . '.tmp';
        file_put_contents($tmp, json_encode($deletecache, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX);
        @rename($tmp, $deletefile);
        
        // assemble json oc preselected and save
        $ocpreselectedcache = array(
            'meta'      => array('file' => $ocproductsfile, 'generated' => time(), 'store_slug' => $store_slug),
            'oc'    => $out
        );
        // write atomically
        $tmp = $ocproductsfile . '.tmp';
        file_put_contents($tmp, json_encode($ocpreselectedcache, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX);
        @rename($tmp, $ocproductsfile);
        
        return array('success' => true, 'error' => false, 'oc' => $out);
        
    }
    
    public function checkOCagainstMPapi($filter = array()) {
        
        ///$store_slug = $this->getStoreSlug(); // however you currently build it
        
        // get the store_slug from mp cache
        //isset($mp_cache['meta']['store_slug']) ? $store_slug = $mp_cache['meta']['store_slug'] : $store_slug = '';
        // alternate way to get store_slug if mp cache not available
        //if($store_slug == '') {
            // load settings & derive store slug
            $this->load->model('setting/setting');
            $settings = $this->model_setting_setting->getSetting('sdx_export_to_mp_sync');
            $api = isset($settings['sdx_export_to_mp_sync_api']) ? $settings['sdx_export_to_mp_sync_api'] : array();
            $store_slug = $this->deriveStoreSlugFromApi($api);
            if (!$store_slug) {
                return array('success' => false, 'error' => 'store_slug_not_found', 'oc' => array()); // return empty array but notify about store-slug error
            }
        //}
        
        // Load helper models
        $this->load->model('catalog/product');
        $this->load->model('localisation/stock_status');
        
        // normalization helper
        $normalizeStr = function($s) {
            $s = trim((string)$s);
            if ($s === '') return '';
            if (function_exists('iconv')) {
                $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
                if ($t !== false) $s = $t;
            }
            $s = mb_strtolower($s, 'UTF-8');
            $s = preg_replace('/\s+/', ' ', $s);
            // remove space to tighten the comparison...
            //$s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $s);
            $s = trim($s);
            return $s;
        };
        
        // 1. Load API products cache
        $cache_info = $this->loadMpApiProductsCache($store_slug, 24); // 24h TTL, for example
        $api_cache = $cache_info['data'];
        $api_cache_file = $cache_info['file'];
        $api_cache_stale = $cache_info['stale'];
        
        if (empty($api_cache)) {
            // No API cache -> set error for UI
            if(isset($this->session->data['error'])){
                $this->session->data['error'] .= '<br>'.$this->language->get('error_mp_products_api'); // add error details if available .(isset($cache_info['error']) ? '<br>'.$cache_info['error'] : '')
            } else {
                $this->session->data['error'] = $this->language->get('error_mp_products_api');
            }
            
            // No API cache -> fallback to XLSX-based version
            return $this->checkOCagainstMP($filter);
        }
        
        // You could also decide to fallback when $cache_info['stale'] == true
        
        // 2. Build index by ext_ref
        //$mpByExtRef = $this->buildMpIndexFromApiCache($api_cache);
        $mp_products = $this->buildMpIndexFromApiCache($api_cache);
        $mpByExtRef = $mp_products['mp_extref'];
        
        // 3. Build MP delete by MP ID
        //$mp_delete = $this->buildMpDeleteFromApiCache($api_cache);
        $mp_delete = $mp_products['mp_delete'];
        
        // 4. Load OC products (same as in checkOCagainstMP)
        //$oc_products = $this->getProductsForMpCheck($filter);
        // get OC products
        $oc_products = $this->getProductsForMP($filter);
        // return empty array if no OC products
        if(empty($oc_products)) {
            return array('success' => false, 'error' => 'Error loading OC products', 'file' => $api_cache_file, 'stale' => $api_cache_stale, 'oc' => array());
        }
        
        $out = array();
        
        foreach ($oc_products as $oc_product) {
            $ext_ref = $oc_product['ext_ref'];
            
            $oc_product_id = (int)$oc_product['product_id_base'];
                
            $pslug = $this->db->query("SELECT `keyword` FROM `" . DB_PREFIX . "url_alias` WHERE query = 'product_id=" . $oc_product_id . "' ")->row;
            $pkeyword = isset($pslug['keyword']) ? $pslug['keyword'] : '';
            $oc_product['product_slug'] = ($pkeyword ? $pkeyword : '');
            $oc_product['view_product'] = ($pkeyword ? HTTPS_CATALOG.$pkeyword : $this->url->link('product/product', 'product_id=' . $oc_product_id, 'SSL'));
            $oc_product['edit_product'] = $this->url->link('catalog/product/update', 'token=' . $this->session->data['token'] . '&product_id=' . $oc_product_id, 'SSL');
            
            // Stock status text
            $stock_status_text = '';
            if ($oc_product['stock_status_id']) {
                $ss = $this->model_localisation_stock_status->getStockStatus( (int)$oc_product['stock_status_id'] );
                if ($ss && isset($ss['name'])) $stock_status_text = $ss['name'];
            }
            $oc_product['stock_status_text'] = $stock_status_text;
            
            $oc_product['specials'] = $this->model_catalog_product->getProductSpecials($oc_product_id);
            
            if (isset($mpByExtRef[$ext_ref])) {
                // Product exists in MP (by ext_ref)
                $mp_product = $mpByExtRef[$ext_ref];
                
                // main sync states
                $mp_sync_status_code = 'in_mp';
                $mp_sync_status = $this->language->get('mp_status_in_mp');
                $mp_matched_by = 'oc_ext_ref';
                $mp_sync_issues = '';
                
                // set mp_sync_status_code + mp_sync_status + mp_matched_by + mp_sync_issues // comparing OC vs $mp_product from API.
                
                // Effective OC price: lowest of special vs regular
                $oc_effective = null;
                if (!empty($oc_product['lowest_special'])) {
                    $oc_effective = $oc_product['lowest_special'];
                } elseif (!empty($oc_product['price'])) {
                    $oc_effective = $oc_product['price'];
                }
                // Effective MP price: lowest of current vs old
                $mp_effective = null;
                if (isset($mp_product['price_gross']) && $mp_product['price_gross'] !== null) {
                    $mp_effective = (float)$mp_product['price_gross'];
                } elseif (isset($mp_product['old_price_gross']) && $mp_product['old_price_gross'] !== null) {
                    $mp_effective = (float)$mp_product['old_price_gross'];
                }
                // Price compare: OC effective vs MP effective 
                if ($oc_effective !== null && $mp_effective !== null && abs($mp_effective - $oc_effective) > 0.01) {
                    $mp_sync_status_code = 'price_stock_diff';
                    $mp_sync_status = $this->language->get('mp_status_price_stock_diff');
                    $mp_sync_issues .= 'mismatch oc_price '.$oc_effective.' vs. mp_price '.$mp_effective.' <br>';
                }
                // Stock
                if ($oc_product['quantity'] !== null && isset($mp_product['stock']) && $mp_product['stock'] !== null) {
                    if ((int)$oc_product['quantity'] !== (int)$mp_product['stock']) {
                        $mp_sync_status_code = 'price_stock_diff';
                        $mp_sync_status = $this->language->get('mp_status_price_stock_diff');
                        $mp_sync_issues .= 'mismatch oc_stock '.$oc_product['quantity'].' vs. mp_stock '.$mp_product['stock'].' <br>';
                    }
                }
                
                // Compare OC ext_ref vs. MP ext_ref 
                if (isset($mp_product['ext_ref'])) {
                    if ($mp_product['ext_ref'] !== '' && $normalizeStr($oc_product['ext_ref']) !== $normalizeStr($mp_product['ext_ref'])) {
                        $mp_sync_status_code = 'out_of_sync';
                        $mp_sync_status = $this->language->get('mp_status_out_of_sync');
                        $mp_sync_issues .= 'mp_ext_ref_mismatch: '.$mp_product['ext_ref'].' <br>';
                    }
                }
                
                // Compare OC Model vs. MP SKU (basic, multi-variant, variant)
                if (isset($mp_product['sku'])) {
                    if ($mp_product['sku'] !== '' && $normalizeStr($oc_product['model']) !== $normalizeStr($mp_product['sku'])) {
                        $mp_sync_status_code = 'out_of_sync';
                        $mp_sync_status = $this->language->get('mp_status_out_of_sync');
                        $mp_sync_issues .= 'mp_sku_mismatch: '.$mp_product['sku'].' <br>';
                    }
                }
                elseif(empty($mp_product['sku'])){
                    $mp_sync_status_code = 'out_of_sync';
                    $mp_sync_status = $this->language->get('mp_status_out_of_sync');
                    $mp_sync_issues .= 'mp_sku_missing: update needed <br>';
                }
                
                // Compare OC Model vs. SKU (variant)
                //if (isset($mp_product['variants']['sku'])) {
                //    if ($mp_product['variants']['sku'] !== '' && $normalizeStr($oc_product['model']) !== $normalizeStr($mp_product['variants']['sku'])) {
                //        $mp_sync_status_code = 'out_of_sync';
                //        $mp_sync_status = $this->language->get('mp_status_out_of_sync');
                //        $mp_sync_issues .= 'mp_variant_sku_mismatch: '.$mp_product['variants']['sku'].' <br>';
                //    }
                //}
                
                // Check OC ID (variant) vs. ext_ref (variant) // not the case as $mpByExtRef/$mp_product are only basic or multi-variant, no variants
                //if (isset($mp_product['variants']['sku'])) {
                //    if ($mp_product['variants']['sku'] !== '' && $normalizeStr($oc_product['model']) !== $normalizeStr($mp_product['variants']['sku'])) {
                //        $mp_sync_status_code = 'out_of_sync';
                //        $mp_sync_status = $this->language->get('mp_status_out_of_sync');
                //        $mp_sync_issues .= 'mp_variant_sku_mismatch: '.$mp_product['variants']['sku'].' <br>';
                //    }
                //}
                
                // Check OC ID vs. ext_ref // not the case as $mpByExtRef/$mp_product are already matched by ext_ref
                //if (isset($mp_product['ext_ref'])) {
                //    if ($mp_product['ext_ref'] !== '' && $normalizeStr($oc_product['model']) !== $normalizeStr($mp_product['ext_ref'])) {
                //        $mp_sync_status_code = 'out_of_sync';
                //        $mp_sync_status = $this->language->get('mp_status_out_of_sync');
                //        $mp_sync_issues .= 'mp_ext_ref_mismatch: '.$mp_product['ext_ref'].' <br>';
                //    }
                //}
                
                // Compare name (basic normalization)
                if (isset($mp_product['name'])) {
                    if ($normalizeStr($oc_product['name']) !== $normalizeStr($mp_product['name'])) {
                        $mp_sync_status_code = 'out_of_sync';
                        $mp_sync_status = $this->language->get('mp_status_out_of_sync');
                        $mp_sync_issues .= 'mp_name_mismatch: '.$mp_product['name'].' <br>';
                    }
                }
                
                $oc_product['mp_id'] = $mp_product['id'];
                
                $oc_product['mp_sync_status_code']  = $mp_sync_status_code;
                $oc_product['mp_sync_status']       = $mp_sync_status;
                $oc_product['mp_matched_by']        = $mp_matched_by;
                $oc_product['mp_sync_issues']       = $mp_sync_issues; // computed issues
                
            } else {
                /* // Check OC variant vs. MP variant
                if (isset($mpByExtRef[$oc_product_id]) && isset($mpByExtRef[$oc_product_id]['variants'])) {
                    
                    $mp_variants = $mpByExtRef[$oc_product_id]['variants'];
                    
                    foreach($mp_variants as $mp_variant) {
                    
                        //if ($mp_variant['sku'] !== '' && $normalizeStr($oc_product['model']) !== $normalizeStr($mp_variant['sku'])) {
                        //    $oc_product['mp_id'] = $mp_variant['id'];
                        //    $oc_product['mp_sync_status_code'] = 'out_of_sync';
                        //    $oc_product['mp_sync_status'] = $this->language->get('mp_status_out_of_sync');
                        //    $oc_product['mp_sync_issues'] = 'mp_variant_sku_mismatch: '.$mp_variant['sku'].' <br>';
                        //    $oc_product['mp_matched_by'] = 'oc_variant_and_product_id';
                        //}
                        
                        if ($mp_variant['sku'] !== '' && $normalizeStr($oc_product['model']) === $normalizeStr($mp_variant['sku'])) {
                            $oc_product['mp_id'] = $mp_variant['id'];
                            $oc_product['mp_sync_status_code'] = 'in_mp';
                            $oc_product['mp_sync_status'] = $this->language->get('mp_status_in_mp');
                            $oc_product['mp_sync_issues'] = 'mp_variant_sku: '.$mp_variant['sku'].' <br>';
                            $oc_product['mp_matched_by'] = 'oc_variant_and_product_id';
                        }
                    }
                }
                */
                //else {
                    // Product Not in MP (by ext_ref) // oc products not found/matched within mp products - to be exported to mp via API POST
                    
                    $oc_product['mp_id'] = null;
                    
                    $oc_product['mp_sync_status_code'] = 'missing';
                    $oc_product['mp_sync_status'] = $this->language->get('mp_status_missing');
                    $oc_product['mp_sync_issues'] = 'not_in_mp_feed';
                    $oc_product['mp_matched_by'] = 'none';
                //}
            }
            
            // Everything else in $oc_product (image, categories, prices etc.) comes from OC, same as now.
            //$out[] = $oc_product;
            $out[$ext_ref] = $oc_product;
        }
        
        // create json files for mp imports via API * Keep the same structure keyed by ext_ref
        $patch  = array();
        $post   = array();
        foreach ($out as $ext_ref => $entry) {
            if ($entry['mp_sync_status_code'] === 'out_of_sync' || $entry['mp_sync_status_code'] === 'price_stock_diff') {
                $patch[$ext_ref] = $entry;
            } elseif ($entry['mp_sync_status_code'] === 'missing') {
                $post[$ext_ref] = $entry;
            }
        }
        
        // delete any existing mp-import_products- json files for mp import
        $mppattern = DIR_LOGS . $store_slug . '_mp-import_products-*.json';
        $mpfiles = glob($mppattern);
        if ($mpfiles) {
            foreach ($mpfiles as $mpf) {
                @unlink($mpf);
            }
        }
        // delete any existing _oc-export_preselected-products_ json files
        $ocpattern = DIR_LOGS . $store_slug . '_oc-export_preselected-products_*.json';
        $ocfiles = glob($ocpattern);
        if ($ocfiles) {
            foreach ($ocfiles as $ocf) {
                @unlink($ocf);
            }
        }
        
        $patchfile      = DIR_LOGS . $store_slug . '_mp-import_products-patch_' . date('Y-m-d') . '.json';
        $postfile       = DIR_LOGS . $store_slug . '_mp-import_products-post_' . date('Y-m-d') . '.json';
        $deletefile     = DIR_LOGS . $store_slug . '_mp-import_products-delete_' . date('Y-m-d') . '.json';
        
        $ocproductsfile = DIR_LOGS . $store_slug . '_oc-export_preselected-products_' . date('Y-m-d') . '.json';
        
        // assemble json patch and save
        $patchcache = array(
            'meta'      => array('file' => $patchfile, 'generated' => time(), 'store_slug' => $store_slug),
            'patch'       => $patch
        );
        // write atomically
        $tmp = $patchfile . '.tmp';
        file_put_contents($tmp, json_encode($patchcache, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX);
        @rename($tmp, $patchfile);
        
        // assemble json post and save
        $postcache = array(
            'meta'      => array('file' => $postfile, 'generated' => time(), 'store_slug' => $store_slug),
            'post'      => $post
        );
        // write atomically
        $tmp = $postfile . '.tmp';
        file_put_contents($tmp, json_encode($postcache, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX);
        @rename($tmp, $postfile);
        
        // assemble json delete and save
        $deletecache = array(
            'meta'      => array('file' => $deletefile, 'generated' => time(), 'store_slug' => $store_slug),
            'delete'    => $mp_delete
        );
        // write atomically
        $tmp = $deletefile . '.tmp';
        file_put_contents($tmp, json_encode($deletecache, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX);
        @rename($tmp, $deletefile);
        
        // assemble json oc preselected and save
        $ocpreselectedcache = array(
            'meta'      => array('file' => $ocproductsfile, 'generated' => time(), 'store_slug' => $store_slug),
            'oc'    => $out
        );
        // write atomically
        $tmp = $ocproductsfile . '.tmp';
        file_put_contents($tmp, json_encode($ocpreselectedcache, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX);
        @rename($tmp, $ocproductsfile);
        
        return array('success' => true, 'error' => false, 'file' => $api_cache_file, 'stale' => $api_cache_stale, 'oc' => $out);
        //return $out;
    }
    
    protected function loadMpApiProductsCache($store_slug, $max_age_hours = 24) {
        $path = $this->getLatestMpApiProductsCachePath($store_slug);
        if (!$path || !file_exists($path)) {
            return array('data' => array(), 'stale' => true, 'file' => null);
        }
        
        $mtime = filemtime($path);
        $stale = false;
        if ($max_age_hours > 0 && (time() - $mtime) > ($max_age_hours * 3600)) {
            $stale = true;
        }
        
        $raw = file_get_contents($path);
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return array('data' => array(), 'stale' => true, 'file' => $path);
        }
        
        return array('data' => $json, 'stale' => $stale, 'file' => $path);
    }
    protected function getLatestMpApiProductsCachePath($store_slug) {
        $pattern = DIR_LOGS . $store_slug . '_mp-export_api-products-cache_*.json';
        $files = glob($pattern);
        if (!$files) {
            return null;
        }
        
        // Filenames contain date, but easiest is “newest by mtime”
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        return $files[0];
    }
    
    protected function buildMpIndexFromApiCache(array $api_cache) {
        $mpByExtRef = array();
        $mpDelete = array();
        $list = array();
        
        if (isset($api_cache['json']['data']) && is_array($api_cache['json']['data'])) {
            $list = $api_cache['json']['data'];
        } else {
            $list = $api_cache; // if raw array of mp products
        }
        
        foreach ($list as $row) {
            if (!is_array($row)) continue;
            
            if (isset($row['ext_ref']) && !empty($row['ext_ref'])) {
                $ext_ref = trim($row['ext_ref']);
                $mpByExtRef[$ext_ref] = $row;
            }
            else {
                $mp_id = $row['id'];
                $mpDelete[$mp_id] = $row;
            }
            
            if (isset($row['variants']) && !empty($row['variants'])) {
                if (!is_array($row['variants'])) continue;
                foreach($row['variants'] as $variant) {
                    if (!is_array($variant)) continue;
                    $variant['type'] = 'variant';
                    $variant['name'] = $row['name'];
                    $variant['category_id'] = $row['category_id'];
                    $variant['category_name'] = $row['category_id'] ? $row['category_name'] : null;
                    $variant['categories'] = $row['category_id'] ? $row['categories'] : null;
                    $variant['status'] = $row['status'];
                    if (isset($variant['ext_ref']) && !empty($variant['ext_ref'])) {
                        $ext_ref = trim($variant['ext_ref']);
                        $mpByExtRef[$ext_ref] = $variant;
                    }
                    else {
                        $mp_id = $variant['id'];
                        $mpDelete[$mp_id] = $variant;
                    }
                }
            }
        }
        
        return array('mp_extref' => $mpByExtRef, 'mp_delete' => $mpDelete);
    }
/*
protected function buildMpDeleteFromApiCache(array $api_cache) {
    $mpDelete = array();
    $list = array();
    
    if (isset($api_cache['json']['data']) && is_array($api_cache['json']['data'])) {
        $list = $api_cache['json']['data'];
    } else {
        $list = $api_cache; // if raw array of products
    }
    
    foreach ($list as $row) {
        
        if (!is_array($row)) continue;
        
        if ($row['ext_ref'] === null) {
            $mp_id = $row['id'];
            $mpDelete[$mp_id] = $row;
        }
        
        if (isset($row['variants']['ext_ref']) && $row['variants']['ext_ref'] === null) {
            $mp_id = $row['variants']['id'];
            $mpDelete[$mp_id] = $row;
        }
        
    }
    
    return $mpDelete;
}
*/
    
    /* === start of get Product details for MerchantPro API actions === */
    
    // Build a minimal OC product row for MP sync. // used for getProductDetailsForMP(). 
    // @param int $product_id * @return array|null
    public function getOcProductRowForMp($product_id) {
        $product_id  = (int)$product_id;
        $language_id = (int)$this->config->get('config_language_id');
        
        // Base product data
        $sql = "SELECT p.product_id, p.model, p.price, p.quantity, p.status, pd.name
                FROM `" . DB_PREFIX . "product` p
                    LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id)
                WHERE p.product_id = '" . $product_id . "'
                    AND pd.language_id = '" . $language_id . "' ";
        
        $q = $this->db->query($sql);
        if (!$q->num_rows) {
            return null;
        }
        
        $row = $q->row;
        
        // Lowest active special price if any
        $lowest_special = $this->getProductLowestSpecial($product_id);
        $lowest_special = !empty($lowest_special) ? $lowest_special : 0.0; 
        
        // Detect product_type:
        // If product has select options, treat as 'variable', else 'simple'
        $opt_q = $this->db->query("
            SELECT po.product_option_id, o.type
            FROM `" . DB_PREFIX . "product_option` po
                LEFT JOIN `" . DB_PREFIX . "option` o ON (po.option_id = o.option_id)
            WHERE po.product_id = '" . $product_id . "'
        ");
        
        $has_select = false;
        foreach ($opt_q->rows as $opt) {
            if (!empty($opt['type']) && $opt['type'] == 'select') {
                $has_select = true;
                break;
            }
        }
        
        $product_type = $has_select ? 'variable' : 'simple';
        //$product_type = empty($this->getProductSelectOptions($product_id)) ? 'simple' : 'variable';
        
        $mp_id = null;
        // get MP products from cache or build that cache
        $mp_cache = $this->getConsolidatedMPcache();
        $mp_products = isset($mp_cache['map']) ? $mp_cache['map'] : array();
        if( isset($mp_products[$product_id]) ) {
            $mp_id = $mp_products[$product_id]['mp_id'];
        }
        
        $ocproduct = array(
            'product_type'    => $product_type,          // simple / variable
            'product_id'      => $product_id,
            'product_id_base' => $product_id,           // for compatibility with your earlier code
            'mp_id'           => $mp_id,                // null => POST, >0 => PATCH
            'model'           => $row['model'],
            'ext_ref'         => $product_id,
            'name'            => $row['name'],
            'price'           => (float)$row['price'],
            'lowest_special'  => $lowest_special,
            'quantity'        => (float)$row['quantity'],
        );
        
        return $ocproduct;
    }
    
    // Build product details suitable for MP API POST/PATCH.
    // @param array $ocproduct -> Row from your OC products list (must contain at least product_type, product_id, product_id_base, mp_id, model, model_base, ext_ref, name, price, lowest_special, quantity).
    public function getProductDetailsForMP($ocproduct) {
        $language_id = (int)$this->config->get('config_language_id');
        
        $error = '';
        
        // --- Decide MP product type based on OC product_type ---
        if (!isset($ocproduct['product_type'])) {
            return array(
                'success'               => false,
                'error'                 => 'Missing OC product_type',
                'product'               => array(),
                'missing_categories'    => array()
            );
        }
        
        if ($ocproduct['product_type'] === 'simple') {
            $mp_type = 'basic';
        } elseif ($ocproduct['product_type'] === 'variable') {
            $mp_type = 'multi_variant';
        } else {
            // We do not support other OC "types" (variant-only rows, undefined, etc.)
            return array(
                'success'               => false,
                'error'                 => 'Cannot use variant or undefined product type (' . $ocproduct['product_type'] . ')',
                'product'               => array(),
                'missing_categories'    => array()
            );
        }
        
        // Base OC product id (the main product, not variant suffix)
        $product_id = isset($ocproduct['product_id_base'])
            ? (int)$ocproduct['product_id_base']
            : (int)$ocproduct['product_id'];
    
        // --- Load main product + description (with your extra columns) ---
        $pdsql = $this->db->query("
            SELECT p.*, pd.*
            FROM `" . DB_PREFIX . "product` p
            LEFT JOIN `" . DB_PREFIX . "product_description` pd
                ON (p.product_id = pd.product_id)
            WHERE pd.language_id = '" . $language_id . "'
              AND p.product_id    = '" . (int)$product_id . "'
        ")->row;
        
        if (!$pdsql) {
            return array(
                'success'               => false,
                'error'                 => 'OC product not found (ID ' . (int)$product_id . ')',
                'product'               => array(),
                'missing_categories'    => array()
            );
        }
        
        // --- Load attributes and build HTML table (your existing helper) ---
        $pasql = $this->db->query("
            SELECT pa.attribute_id, ad.name, pa.text, pa.filterseo
            FROM " . DB_PREFIX . "product_attribute pa
            LEFT JOIN " . DB_PREFIX . "attribute_description ad
                   ON (pa.attribute_id = ad.attribute_id
                   AND ad.language_id = '" . $language_id . "')
            WHERE pa.product_id = '" . (int)$product_id . "'
        ")->rows;
        
        $pattrtable = $this->buildAttributesHtmlTable($pasql);
        
        // --- Build full HTML description (OC -> MP) ---
        $description_parts = array();
        
        if (!empty($pdsql['description'])) {
            $description_parts[] = $this->cleanhtml($pdsql['description']);
        }
        if (!empty($pattrtable)) {
            $description_parts[] = $pattrtable;
        }
        if (!empty($pdsql['specificatii'])) {
            $description_parts[] = $this->cleanhtml($pdsql['specificatii']);
        }
        if (!empty($pdsql['aplicatii'])) {
            $description_parts[] = $this->cleanhtml($pdsql['aplicatii']);
        }
        
        $description = '';
        if (!empty($description_parts)) {
            $description = implode('<hr>', $description_parts);
        }
        
        // --- Meta fields ---
        $meta_title = isset($ocproduct['name']) ? $ocproduct['name'] : $pdsql['name'];
        
        if (!empty($pdsql['meta_description'])) {
            $meta_description = $this->cleantxt($pdsql['meta_description']);
        } elseif (!empty($pattrtable)) {
            $meta_description = $this->cleantxt($pattrtable);
        } else {
            $meta_description = '';
        }
        
        // --- Identity (sku, ext_ref) ---
        $sku = isset($ocproduct['model']) ? $ocproduct['model'] : (isset($pdsql['model']) ? $pdsql['model'] : '');
        $ext_ref = isset($ocproduct['ext_ref']) ? $ocproduct['ext_ref'] : $product_id;
        
        if ($sku === '') {
            $error .= ($error ? ' / ' : '') . 'OC product model is empty';
        }
        if ($ext_ref === '' || $ext_ref === null) {
            $ext_ref = $product_id;
        }
        if (empty($ocproduct['name']) && empty($pdsql['name'])) {
            $error .= ($error ? ' / ' : '') . 'OC product name is empty';
        }
        
        $product_name = isset($ocproduct['name']) ? $ocproduct['name'] : $pdsql['name'];
        
        // --- TAX: read TVA from MP cached taxonomy JSON ---
        $mpTax = $this->getMpTvaFromCache();
        $mp_tva_value = isset($mpTax['value']) ? (float)$mpTax['value'] : 21.0; // 0.0 or 21.0 as fallback for Romania 2025
        if ($mp_tva_value <= 0) {
            // Fallback TVA (adjust if your MP shop uses another value)
            $mp_tva_value = 21.0;
        }
        
        // --- Base prices (gross/net) ---
        $price_source_gross = 0.0;
        if (isset($ocproduct['lowest_special']) && (float)$ocproduct['lowest_special'] > 0) {
            // promo price
            $price_source_gross = (float)$ocproduct['lowest_special'];
        } else {
            $price_source_gross = isset($ocproduct['price']) ? (float)$ocproduct['price'] : (float)$pdsql['price'];
        }
        
        $price_gross = $price_source_gross;
        $price_net   = ($price_gross > 0)
            ? round($price_gross / (1 + $mp_tva_value / 100), 4)
            : 0.0;
        
        $old_price_gross = '';
        $old_price_net   = '';
        
        if (isset($ocproduct['lowest_special']) && (float)$ocproduct['lowest_special'] > 0) {
            // oc price is "old" price
            $old_price_gross = isset($ocproduct['price']) ? (float)$ocproduct['price'] : (float)$pdsql['price'];
            $old_price_net   = round($old_price_gross / (1 + $mp_tva_value / 100), 4);
        }
        
        // --- Optional cost (if you have a cost column) ---
        $pcost_gross = isset($pdsql['cost']) ? (float)$pdsql['cost'] : 0.0;
        $cost_gross  = $pcost_gross > 0 ? $pcost_gross : null;
        $cost_net    = ($cost_gross !== null && $cost_gross > 0)
            ? round($cost_gross / (1 + $mp_tva_value / 100), 4)
            : null;
        
        // --- Stock & inventory ---
        $quantity = isset($ocproduct['quantity'])
            ? (float)$ocproduct['quantity']
            : (isset($pdsql['quantity']) ? (float)$pdsql['quantity'] : 0.0);
        
        $inventory_enabled = 'on'; // on/off as per MP docs
        $allow_backorders  = true;
        
        // --- Weight (assuming already normalized to kg elsewhere) ---
        $weight = isset($pdsql['weight']) ? (float)$pdsql['weight'] : 0.0;
        
        // --- Quantity multiplier ---
        $qty_multiplier = 1;
        if (!empty($pdsql['minimum']) && (int)$pdsql['minimum'] > 1) {
            $qty_multiplier = (int)$pdsql['minimum'];
        } elseif (!empty($pdsql['name']) && stripos($pdsql['name'], 'banda led') !== false) {
            $qty_multiplier = 5;
        }
        
        // --- Categories: map OC category_ids -> MP category_id + categories[] ---
        $oc_categories = array();
        $qCats = $this->db->query("
            SELECT category_id
            FROM `" . DB_PREFIX . "product_to_category`
            WHERE product_id = '" . (int)$product_id . "'
        ");
        foreach ($qCats->rows as $r) {
            $oc_categories[] = (int)$r['category_id'];
        }
        
        $ocToMpCat   = $this->getOcToMpCategoryMapFromJson();
        $mpCatNames  = $this->getMpCategoryNameMapFromCache();
        
        $categories            = array();
        $primary_category_id   = 0;
        $missing_categories    = array();
        
        foreach ($oc_categories as $cid) {
            if (isset($ocToMpCat[$cid])) {
                $mp_id = (int)$ocToMpCat[$cid];
                if (!$primary_category_id) {
                    $primary_category_id = $mp_id;
                }
                $cat = array('id' => $mp_id);
                if (isset($mpCatNames[$mp_id])) {
                    $cat['name'] = $mpCatNames[$mp_id];
                }
                $categories[] = $cat;
            } else {
                // Keep track of OC categories which are not yet mapped to MP
                $missing_categories[] = $cid;
            }
        }
        
        // --- Base MP product payload (no variants yet) ---
        $product = array(
            // For PATCH, MP expects id here. For POST, omit or keep null.
            'id'               => isset($ocproduct['mp_id']) && $ocproduct['mp_id']
                                    ? (int)$ocproduct['mp_id']
                                    : null,
            
            'type'             => $mp_type,
            
            'sku'              => $sku,
            'ext_ref'          => $ext_ref,
            'name'             => $product_name,
            'description'      => $description,
            'meta_title'       => $meta_title,
            'meta_description' => $meta_description,
            
            'inventory_enabled' => $inventory_enabled,
            'stock'             => $quantity,
            'stock_reserved'    => 0,
            'allow_backorders'  => $allow_backorders,
            
            'price_net'         => $price_net,
            'price_gross'       => $price_gross,
            'old_price_net'     => $old_price_net,
            'old_price_gross'   => $old_price_gross,
            
            'cost_net'          => $cost_net,
            'cost_gross'        => $cost_gross,
            
            'tax_id'            => isset($mpTax['id']) ? (int)$mpTax['id'] : null,
            'tax_value'         => $mp_tva_value,
            'tax_name'          => isset($mpTax['name']) ? $mpTax['name'] : null,
            
            'quantity_multiplier' => $qty_multiplier,
            'weight'              => $weight,
            
            'category_id'        => $primary_category_id ?: null,
            'category_name'      => $primary_category_id && isset($mpCatNames[$primary_category_id])
                                    ? $mpCatNames[$primary_category_id]
                                    : null,
            'categories'         => $categories,
        );
        
        // --- Multi-variant product: build variant_attributes + variants from OC options ---
        if ($mp_type === 'multi_variant') {
            $select_options = $this->getProductSelectOptions($product_id);
            
            if (!empty($select_options)) {
                $variant_data = $this->buildMpVariantsFromOcOptions(
                    $ocproduct,
                    $select_options,
                    $mpTax,
                    $price_net,
                    $price_gross
                );
                $product['variant_attributes'] = $variant_data['variant_attributes'];
                $product['variants']           = $variant_data['variants'];
            } else {
                // If OC says "variable" but there are no select options, fall back to basic
                $product['type'] = 'basic';
            }
        }
        
        // success is true only if we do not have hard errors and all OC categories are mapped
        $success = ($error === '' && empty($missing_categories));
        
        return array(
            'success'            => $success,
            'error'              => $error,
            'product'            => $product,            // MP-ready payload
            'missing_categories' => $missing_categories, // list of OC category_ids that have no MP mapping
        );
    }
    
    // Read TVA tax from cached taxonomy JSON (*_mp-export_taxes-cache_*.json)
    public function getMpTvaFromCache() {
        
        $storeslug = $this->getMpStoreSlugFromSettings();
        //$cache = array('id' => null, 'value' => 0.0, 'name' => '', 'file' => null);
        $cache = array('id' => 1, 'value' => 21.0, 'name' => 'TVA', 'file' => null); // fallback for Romania as 2025
        if ($storeslug === '') {
            return $cache;
        }
        
        $pattern = DIR_LOGS . $storeslug . '_mp-export_taxes-cache_*.json';
        $files   = glob($pattern);
        if (!$files) {
            return $cache;
        }
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $file = $files[0];
        $raw  = @file_get_contents($file);
        if ($raw === false) {
            return $cache;
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return $cache;
        }
        // Try to locate list of taxes
        $rows = array();
        if (isset($json['json']) && is_array($json['json']) && !isset($json['json']['error']) ) {
            $rows = $json['json'];
        }
        if (!$rows) {
            return $cache;
        }
        
        $candidates = array();
        foreach ($rows as $t) {
            if (!is_array($t)) continue;
            
            $id   = isset($t['id'])    ? (int)$t['id']    : 0;
            $name = isset($t['name'])  ? trim($t['name']) : '';
            $val  = isset($t['value']) ? (float)$t['value'] : null;
            
            if ($id <= 0 || $name === '' || $val === null) continue;
            
            $row = array('id' => $id, 'value' => $val, 'name' => $name, 'file' => $file);
            
            $lname = strtolower($name);
            if (strpos($lname, 'tva') !== false || strpos($lname, 'vat') !== false) {
                $candidates[] = $row;
            }
        }
        
        if (!empty($candidates)) {
            // If multiple TVA-like rates exist, pick the highest value (usually main VAT)
            usort($candidates, function($a, $b) {
                if ($a['value'] == $b['value']) return 0;
                return ($a['value'] < $b['value']) ? 1 : -1;
            });
            $cache = $candidates[0];
            return $cache;
        }
        
        // Fallback: use first tax row
        $first = reset($rows);
        if (is_array($first)) {
            $cache = array(
                'id'    => isset($first['id'])    ? (int)$first['id']    : 1, // null or 1 as fallback 
                'value' => isset($first['value']) ? (float)$first['value'] : 21.0, // 0.0 or 21.0 as fallback as for Romania in 2025
                'name'  => isset($first['name'])  ? $first['name']       : 'TVA', // mepty or TVA as fallback for Romania
                'file'  => $file,
            );
        }
        
        return $cache;
    }
    
    // Map OC category_id → MP category_id via {slug}_mp-export_categories-sync_PATCH_YYYY-MM-DD.json
    // Uses the JSON files produced by computeCategorySyncStatus()
    protected function getOcToMpCategoryMapFromJson() {
        static $map_cache = null;
        
        if ($map_cache !== null) {
            return $map_cache;
        }
        
        $map_cache = array();
        
        $slug = $this->getMpStoreSlugFromSettings();
        if ($slug === '') {
            return $map_cache;
        }
        
        // Files like: {slug}_mp-export_categories-sync_PATCH_YYYY-MM-DD.json
        $pattern = DIR_LOGS . $slug . '_mp-export_categories-sync_PATCH_*.json';
        $files   = glob($pattern);
        if (!$files) {
            return $map_cache;
        }
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $file = $files[0];
        $raw  = @file_get_contents($file);
        if ($raw === false) {
            return $map_cache;
        }
        $json = json_decode($raw, true);
        if (!is_array($json) || !isset($json['items']) || !is_array($json['items'])) {
            return $map_cache;
        }
        foreach ($json['items'] as $item) {
            if (!is_array($item)) continue;
            
            if (!isset($item['oc_category_id'])) continue;
            
            $cid  = (int)$item['oc_category_id'];
            $mpId = 0;
            
            if (isset($item['mp_id']) && (int)$item['mp_id'] > 0) {
                $mpId = (int)$item['mp_id'];
            } elseif (isset($item['payload']) && is_array($item['payload']) && isset($item['payload']['id'])) {
                $mpId = (int)$item['payload']['id'];
            }
            
            if ($cid > 0 && $mpId > 0) {
                $map_cache[$cid] = $mpId;
            }
        }
        
        return $map_cache;
    }
    
    // MP category id → name map from *_mp-export_all-categories-cache_*.json
    // This helps to fill category_name and categories[].name
    protected function getMpCategoryNameMapFromCache() {
        
        static $name_cache = null;
        
        if ($name_cache !== null) {
            return $name_cache;
        }
        
        $name_cache = array();
        
        $slug = $this->getMpStoreSlugFromSettings();
        if ($slug === '') {
            return $name_cache;
        }
        
        // Files like: {slug}_mp-export_all-categories-cache_YYYY-MM-DD.json
        $pattern = DIR_LOGS . $slug . '_mp-export_all-categories-cache_*.json';
        $files   = glob($pattern);
        if (!$files) {
            return $name_cache;
        }
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $file = $files[0];
        $raw  = @file_get_contents($file);
        if ($raw === false) {
            return $name_cache;
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return $name_cache;
        }
        
        if (isset($json['json']) && is_array($json['json']) && isset($json['json']['data']) && is_array($json['json']['data'])) {
            foreach ($json['json']['data'] as $cat) {
                if (!is_array($cat) || !isset($cat['id'])) continue;
                
                $id   = (int)$cat['id'];
                $name = isset($cat['name']) ? $cat['name'] : '';
                
                $name_cache[$id] = $name;
            }
        }
        
        return $name_cache;
    }
    
    // helper for multi-variant : build variant_attributes + variants
    // converts getProductSelectOptions() output (select-type OC options) into MP’s variant_attributes and variants structure.
    protected function buildMpVariantsFromOcOptions($ocproduct, $select_options, $mpTax, $base_price_net, $base_price_gross) {
        $variant_attributes = array();
        $variants           = array();
        
        $mp_tva_value = isset($mpTax['value']) ? (float)$mpTax['value'] : 0.0;
        if ($mp_tva_value <= 0) {
            $mp_tva_value = 21.0; // fallback as for Romania 2025
        }
        
        // 1) Build variant_attributes[] from all select options
        foreach ($select_options as $sel) {
            if (!isset($sel['name'])) continue;
            
            $attrName = trim($sel['name']);
            if ($attrName === '') continue;
            
            if (!isset($variant_attributes[$attrName])) {
                $variant_attributes[$attrName] = array(
                    'name'    => $attrName,
                    'options' => array()
                );
            }
            
            if (!empty($sel['options']) && is_array($sel['options'])) {
                foreach ($sel['options'] as $opt) {
                    $val = isset($opt['option']) ? trim($opt['option']) : '';
                    if ($val === '') continue;
                    
                    // de-duplicate per attribute
                    if (!isset($variant_attributes[$attrName]['options'][$val])) {
                        $variant_attributes[$attrName]['options'][$val] = array(
                            // MP docs: options[].value; id/position/available are optional when creating
                            'value' => $val
                        );
                    }
                }
            }
        }
        
        // Flatten options
        foreach ($variant_attributes as $k => $attr) {
            $variant_attributes[$k]['options'] = array_values($attr['options']);
        }
        $variant_attributes = array_values($variant_attributes);
        
        // 2) Build variants[] – one variant per option
        // NOTE: This handles the common case with one select attribute.
        // If you have multiple select attributes, you may want to expand full combinations later.
        foreach ($select_options as $sel) {
            if (!isset($sel['name'])) continue;
            
            $attrName = trim($sel['name']);
            if ($attrName === '') continue;
            
            if (empty($sel['options']) || !is_array($sel['options'])) continue;
            
            foreach ($sel['options'] as $opt) {
                $value = isset($opt['option']) ? trim($opt['option']) : '';
                if ($value === '') continue;
                
                $suffix = $this->slugifyForExtRef($value);
                
                // Variant gross price from base + option diff
                $variant_price_gross = $base_price_gross;
                if (isset($opt['price']) && (float)$opt['price'] != 0 && isset($opt['price_prefix'])) {
                    if ($opt['price_prefix'] === '+') {
                        $variant_price_gross += (float)$opt['price'];
                    } elseif ($opt['price_prefix'] === '-') {
                        $variant_price_gross -= (float)$opt['price'];
                    }
                }
                if ($variant_price_gross < 0) {
                    $variant_price_gross = 0;
                }
                
                $variant_price_net = ($variant_price_gross > 0)
                    ? round($variant_price_gross / (1 + $mp_tva_value / 100), 4)
                    : 0.0;
                
                $variant_quantity = isset($opt['quantity'])
                    ? (int)$opt['quantity']
                    : (isset($ocproduct['quantity']) ? (int)$ocproduct['quantity'] : 0);
                
                $base_sku  = isset($ocproduct['model'])   ? $ocproduct['model']   : $ocproduct['model_base'];
                $base_ref  = isset($ocproduct['ext_ref']) ? $ocproduct['ext_ref'] : $ocproduct['product_id_base'];
                
                $variant_sku = $base_sku . '_' . $suffix;
                $variant_ext_ref = $base_ref !== '' ? $base_ref . '_' . $suffix : $ocproduct['product_id_base'] . '_' . $suffix;
                
                $variants[] = array(
                    // For create we usually omit id; for PATCH you can fill it later if you fetch them from MP
                    'sku'               => $variant_sku,
                    'ext_ref'           => $variant_ext_ref,
                    'inventory_enabled' => 'on',
                    'stock'             => $variant_quantity,
                    
                    'price_net'         => $variant_price_net,
                    'price_gross'       => $variant_price_gross,
                    
                    // You can add old_price_* here if you also compute variant-specific discounts
                    'variant_options'   => array(
                        array(
                            // MP docs: id/option_id are optional on create, so we send name + value
                            'name'  => $attrName,
                            'value' => $value
                        )
                    )
                );
            }
        }
        
        return array(
            'variant_attributes' => $variant_attributes,
            'variants'           => $variants
        );
    }
    
    /* === end of get Product details for MerchantPro API actions === */
    
    // general helper to get clean html
    public function cleanhtml($html) {
        // Ensure UTF-8
        if (!mb_detect_encoding($html, 'UTF-8', true)) {
            $html = mb_convert_encoding($html, 'UTF-8', 'auto');
        }
        
        // Decode HTML entities
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Normalize and remove diacritics / control characters
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($html, Normalizer::NFD);
            $html = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $normalized);
        }
        
        // Remove script, style, and xml blocks entirely
        $html = preg_replace('#<(script|style|xml)\b[^>]*>.*?</\1>#is', ' ', $html);
        
        // Remove tabs and normalize newlines to spaces
        $html = str_replace(["\t", "\r", "\n"], ' ', $html);
        
        // Replace non-breaking spaces
        $html = str_replace('&nbsp;', ' ', $html);
        
        // Remove empty tags commonly found in Word / HTML editors
        $emptyTags = ['b', 'p', 'div', 'o:p'];
        foreach ($emptyTags as $tag) {
            $html = preg_replace('#<' . $tag . '>\s*</' . $tag . '>#i', '', $html);
            $html = preg_replace('#<' . $tag . '><br\s*/?></' . $tag . '>#i', '', $html);
        }
        
        // Remove span, font, strong styling
        $html = preg_replace('#<span[^>]*>#i', '', $html);
        $html = str_replace('</span>', '', $html);
        
        $html = preg_replace('#<font[^>]*>#i', '', $html);
        $html = str_replace('</font>', '', $html);
        
        $html = preg_replace('#<strong[^>]*>#i', '<b>', $html);
        $html = preg_replace('#</strong[^>]*>#i', '</b>', $html);
        
        // Remove styling attributes from tags
        $attributesToRemove = ['style', 'width', 'height', 'class', 'lang', 'title'];
        foreach ($attributesToRemove as $attr) {
            $html = preg_replace('/(<[^>]+)\s' . $attr . '=".*?"/i', '$1', $html);
        }
        
        // Normalize headers, paragraphs, divs, hr, br
        $html = preg_replace('#<h[1-6][^>]*>#i', '<h$1>', $html);
        $html = preg_replace('#<p[^>]*>#i', '<p>', $html);
        $html = preg_replace('#<div[^>]*>#i', '<div>', $html);
        $html = preg_replace('#<br[^>]*>#i', '<br>', $html);
        $html = preg_replace('#<hr[^>]*>#i', '<hr>', $html);
        
        // Fix image src and links (replace spaces with %20)
        $html = preg_replace_callback(
            '/(src|href)="(.*?)"/i',
            function ($matches) {
                $url = preg_replace('/\s+/', '%20', $matches[2]);
                return $matches[1] . '="' . $url . '"';
            },
            $html
        );
        
        // Force https and normalize domain example
        $html = str_replace('http:', 'https:', $html);
        $html = str_replace('../image/data', HTTPS_CATALOG.'image/data', $html);
        $html = str_replace('//led-zone.ro', '//www.led-zone.ro', $html); // led-zone specific
        
        // Normalize whitespace
        $html = preg_replace('/ {2,}/', ' ', $html);
        $html = trim($html);
        
        return $html;
    }
    
    // general helper to get clean text
    public function cleantxt($string) {
        if (method_exists($this, 'cleanhtml')) {
            $string = $this->cleanhtml($string);
        } else {
            // Ensure UTF-8
            if (!mb_detect_encoding($string, 'UTF-8', true)) {
                $string = mb_convert_encoding($string, 'UTF-8', 'auto');
            }
            
            // Decode html if necessary
            $string = html_entity_decode($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            // Normalize and remove diacritics/control characters
            if (class_exists('Normalizer')) {
                $normalized = Normalizer::normalize($string, Normalizer::NFD);
                $string = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $normalized);
            }
            
            // Remove <script> and <style> blocks entirely
            //$string = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $string);
            // Remove <script>, <style> and <xml> blocks entirely
            $string = preg_replace('#<(script|style|xml)\b[^>]*>.*?</\1>#is', ' ', $string);
            
            // Remove tabs and normalize newlines to spaces
            $string = str_replace(["\t", "\r", "\n"], ' ', $string);
            
            // Replace non-breaking spaces
            $string = str_replace('&nbsp;', ' ', $string);
            
            // Remove empty tags commonly found in Word / HTML editors
            $emptyTags = ['b', 'p', 'div', 'o:p'];
            foreach ($emptyTags as $tag) {
                $string = preg_replace('#<' . $tag . '>\s*</' . $tag . '>#i', '', $string);
                $string = preg_replace('#<' . $tag . '><br\s*/?></' . $tag . '>#i', '', $string);
            }
        }
        
        // Handle tables
        if (stripos($string, '<table') !== false) {
            // Process each table row
            $string = preg_replace_callback('#<table[^>]*>(.*?)</table>#is', function($tableMatch) {
                $tableContent = $tableMatch[1];
                
                // Extract rows
                preg_match_all('#<tr[^>]*>(.*?)</tr>#is', $tableContent, $rows);
                
                $rowTexts = [];
                foreach ($rows[1] as $row) {
                    // Extract cells
                    preg_match_all('#<t[dh][^>]*>(.*?)</t[dh]>#is', $row, $cells);
                    $cellTexts = array_map(function($cell) {
                        return trim(strip_tags($cell));
                    }, $cells[1]);
                    
                    if (!$cellTexts) continue;
                    
                    // Join cells with "::", but no trailing "::" at end
                    $rowTexts[] = implode('::', $cellTexts);
                }
                
                // Join rows with ", " and add dot at the end
                return implode(', ', $rowTexts) . '. ';
            }, $string);
        }
        /*if (stripos($string, '<table') !== false) {
            // Table cells → "::"
            $string = preg_replace('/<\/t[dh]>/i', '::', $string);
            // Table rows → ", "
            $string = preg_replace('/<\/tr>/i', ', ', $string);
            // Drop table wrappers → replaced with ". "
            $string = preg_replace('/<\/?table[^>]*>/i', '. ', $string);
            $string = preg_replace('/<\/?(thead|tbody|tr|th|td)[^>]*>/i', '', $string);
        }*/
        
        // Handle unordered lists: replace <li> with bullet and remove <ul>/<ol>
        if (stripos($string, '<ul') !== false || stripos($string, '<ol') !== false) {
            // Detect <ol> numbering
            $string = preg_replace_callback('#<ol[^>]*>(.*?)</ol>#is', function($matches) {
                $content = $matches[1];
                preg_match_all('#<li[^>]*>(.*?)</li>#is', $content, $items);
                $output = '';
                foreach ($items[1] as $index => $item) {
                    $text = strip_tags($item);
                    $output .= ($index + 1) . '. ' . trim($text) . ' ';
                }
                return $output;
            }, $string);
            
            // Handle <ul>
            $string = preg_replace_callback('#<ul[^>]*>(.*?)</ul>#is', function($matches) {
                $content = $matches[1];
                preg_match_all('#<li[^>]*>(.*?)</li>#is', $content, $items);
                $output = '';
                foreach ($items[1] as $item) {
                    $text = strip_tags($item);
                    $output .= '• ' . trim($text) . ' ';
                }
                return $output;
            }, $string);
        }
        
        // Remove any remaining HTML tags
        $string = strip_tags($string);
        
        // Replace &nbsp; with spaces
        $string = str_replace('&nbsp;', ' ', $string);
        
        // Normalize whitespace
        $string = str_replace(["\r", "\t", "\n"], ' ', $string);
        $string = preg_replace('/ {2,}/', ' ', $string);
        
        // Cleanup separators
        $string = preg_replace('/(::)+/', '::', $string);  // collapse multiple "::"
        //$string = preg_replace('/.+/', '. ', $string);     // collapse multiple dots
        $string = preg_replace('/,+/', ', ', $string);     // collapse multiple commas
        $string = preg_replace('/\s+,/', ',', $string);    // fix space before comma
        $string = trim($string, ",: ");
        
        return trim($string);
    }
    
    // general helper to get html table from OC product attributes
    public function buildAttributesHtmlTable($attrs) {
        if (empty($attrs)) return '';
        $rows = array();
        foreach ($attrs as $a) {
            $n = isset($a['name']) ? $a['name'] : '';
            $t = isset($a['text']) ? $a['text'] : '';
            if ($n === '' && $t === '') continue;
            $rows[] = '<tr><td>' . htmlspecialchars($n, ENT_QUOTES, 'UTF-8') . '</td><td>' . $t . '</td></tr>';
        }
        if (empty($rows)) return '';
        return '<table>' . implode('', $rows) . '</table>';
    }
    
    // general helper to get the MP store slug from MP API settings (based on MP store url)
    protected function getMpStoreSlugFromSettings() {
        // Same logic as controller->mpApiSettings(), but trimmed down to only need the URL
        if (!isset($this->model_setting_setting)) {
            $this->load->model('setting/setting');
        }
    
        $settings = $this->model_setting_setting->getSetting('sdx_export_to_mp_sync');
    
        $api = isset($settings['sdx_export_to_mp_sync_api'])
            ? $settings['sdx_export_to_mp_sync_api']
            : array();
    
        if (!$api && $this->config->get('sdx_export_to_mp_sync_api')) {
            $api = $this->config->get('sdx_export_to_mp_sync_api');
        }
    
        $base = isset($api['mp_api_url']) ? rtrim($api['mp_api_url'], '/') : '';
    
        if ($base === '') {
            // Fallback – you could derive from shop URL if needed
            return '';
        }
    
        // Uses your existing helper
        return $this->deriveStoreSlugFromUrl($base);
    }
    
}

?>