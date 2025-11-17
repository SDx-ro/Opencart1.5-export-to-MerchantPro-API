<?php

/* v1.4 model SDxExportToMPSync */

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
                LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "')
                WHERE 1=1";
        
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
    
    /* // categories with path for filter selector
    public function getCategoriesWithPath() {
        
        $query = $this->db->query("SELECT c.category_id, c.parent_id, cd.name
                                   FROM " . DB_PREFIX . "category c
                                   JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id)
                                   WHERE cd.language_id = '" . (int)$this->config->get('config_language_id') . "' ");
        
        $rows = $query->rows;
        $cats = array();
        foreach ($rows as $r) {
            $cats[$r['category_id']] = array('category_id' => $r['category_id'], 'parent_id' => $r['parent_id'], 'name' => $r['name']);
        }
        
        $build = function($cid) use (&$cats, &$build) {
            if (!isset($cats[$cid])) return '';
            $name = $cats[$cid]['name'];
            if ($cats[$cid]['parent_id'] && isset($cats[$cats[$cid]['parent_id']])) {
                $parent = $build($cats[$cid]['parent_id']);
                if ($parent) return $parent . ' > ' . $name;
            }
            return $name;
        };
        
        $out = array();
        foreach ($cats as $cid => $cinfo) {
            $out[] = array('category_id' => $cid, 'name' => $cinfo['name'], 'path' => $build($cid));
        }
        
        usort($out, function($a, $b){ return strcasecmp($a['name'], $b['name']); });
        
        return $out;
        
    }
    */
    // categories with path for filter selector
    public function getCategoriesWithPath() {
        
        $query = $this->db->query("SELECT c.category_id, c.parent_id, c.status, cd.name
                                   FROM " . DB_PREFIX . "category c
                                   JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id)
                                   WHERE cd.language_id = '" . (int)$this->config->get('config_language_id') . "' ");
        
        $rows = $query->rows;
        $cats = array();
        foreach ($rows as $r) {
            $cats[$r['category_id']] = array(
                'category_id' => (int)$r['category_id'],
                'parent_id'   => (int)$r['parent_id'],
                'status'      => (int)$r['status'],
                'name'        => $r['name']
            );
        }
        
        $build = function($cid) use (&$cats, &$build) {
            if (!isset($cats[$cid])) return '';
            $name = $cats[$cid]['name'];
            if ($cats[$cid]['parent_id'] && isset($cats[$cats[$cid]['parent_id']])) {
                $parent = $build($cats[$cid]['parent_id']);
                if ($parent) return $parent . ' > ' . $name;
            }
            return $name;
        };
        
        $out = array();
        foreach ($cats as $cid => $cinfo) {
            $out[] = array(
                'category_id' => $cid,
                'parent_id'   => $cinfo['parent_id'],
                'status'      => $cinfo['status'],
                'name'        => $cinfo['name'],
                'path'        => $build($cid)
            );
        }
        
        usort($out, function($a, $b){
            //return strcasecmp($a['name'], $b['name']); // sort by name
            return strcasecmp($a['path'], $b['path']); // sort by path
        });
        
        return $out;
    }
    
    /* === start of get and update MerchantPro consolidated Feed === */
    /**
     * Update MerchantPro merged feed:
     * - read two feeds (simple+variable and variants) via SimpleXLSX::parse($url)
     * - merge them using matching rules
     * - save single merged XLSX in DIR_LOGS with name {store_slug}_mp-export_feed-all-products_{YYYY-MM-DD}.xlsx
     * Returns array('success'=>bool, 'filename'=>..., 'filepath'=>..., 'error'=>...)
    */
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
        $this->downloadFile($feed_simple, $local_feed_simple);
        $this->downloadFile($feed_variants, $local_feed_variants);
        
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
    
    // Convert memory_limit to bytes
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
    
    protected function downloadFile($url, $dest) {
        $ch = curl_init($url);
        $fp = fopen($dest, 'w+');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60s timeout
        curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        curl_close($ch);
        fclose($fp);
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
                            'mpn'               => $mpn,
                            'mpn_base'          => !empty($mpn) ? $mpn . '_' . $suffix : '',
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
    
    // product categories paths (array of "Parent > Child")
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
    
    // helper: build category path (Parent > Child)
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
    
    // product categories plain (no path)
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
    
    // builds a safe slug (ASCII, no spaces, dash separated, optionally lower-case) for variant suffixes * Example: "Alb Cald" -> "Alb-Cald" * Note: original case style is kept; for safety, diacritics are remove and spaces are replaced with '-'
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
    
    // get consolidated MP feed map (with cache) * Returns array('success'=>true, 'cachefile'=>..., 'map'=>..., 'meta'=>...) or error
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
        } else { $cachefile = DIR_LOGS . $store_slug . '_mp-export_all-products-cache_' . date('Y-m-d') . '.json'; }
        
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
            
            // collect mp products with missing ext_ref (added manually, left-overs in mp - potentially out-of-sync)
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
            'map'       => $map,
            'mp_extra'  => $mp_extra
        );
        
        // delete any existing $cachepattern json files
        $dfiles = glob($cachepattern);
        if ($dfiles) { foreach ($dfiles as $df) { @unlink($df); } }
        
        // write $cachefile file atomically
        $tmp = $cachefile . '.tmp';
        file_put_contents($tmp, json_encode($cache, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX);
        @rename($tmp, $cachefile);
        
        return array('success' => true, 'cachefile' => $cachefile, 'map' => $map, 'mp_extra'  => $mp_extra, 'meta' => $cache['meta']);
    }
    
    public function checkOCagainstMP($filter = array(), $force_rebuild_cache = false) {
        
        // initialize the output...
        $out = array();
        
        // Load helper models
        $this->load->model('catalog/product');
        $this->load->model('localisation/stock_status');
        
        // get OC products
        $oc_products = $this->getProductsForMP($filter);
        // return empty array if no OC products...
        if(empty($oc_products)) {
            return array('success' => false, 'error' => 'Error loading OC products', 'oc' => $out);
        }
        
        // get MP products from cache or build that cache
        $mp_cache = $this->getConsolidatedMPcache($force_rebuild_cache);
        // no mp feed...
        if (empty($mp_cache['success'])) {
            // no feed -> mark all as 'no_feed'
            foreach ($oc_products as $oc_product) {
                // $oc_product must be array... maybe check this...
                
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
                
                $oc_product['mp_sync_status_code'] = 'no_feed'; // available $mp_sync_status_code: no_feed, missing, in_mp, in_mp_by_sku, collision, out_of_sync, price_stock_diff
                $oc_product['mp_sync_status'] = $this->language->get('mp_status_no_feed');
                $oc_product['mp_sync_issues'] = isset($mp_cache['error']) ? $mp_cache['error'] : $this->language->get('mp_status_no_feed');
                $oc_product['mp_matched_by'] = 'none';
                
                $out[$oc_ext_ref] = $oc_product;
            }
            return array('success' => false, 'error' => (isset($mp_cache['error']) ? $mp_cache['error'] : $this->language->get('mp_status_no_feed')), 'oc' => $out);
        }
        // mp feed with known ext_ref, array of mp products with ext_ref key
        $mp_products = isset($mp_cache['map']) ? $mp_cache['map'] : array();
        
        // normalization helpers
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
        
        foreach ($oc_products as $ext => $oc_product) {
            // $oc_product must be array... maybe check this...
            
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
            
            //..
            
        }
        
        // create json files for mp imports via API * Keep the same structure keyed by ext_ref
        $patch  = array();
        $post   = array();
        $delete = array();
        foreach ($out as $ext_ref => $entry) {
            $code = isset($entry['mp_sync_status_code']) ? $entry['mp_sync_status_code'] : '';
            if ($code === 'out_of_sync' || $code === 'price_stock_diff') {
                $patch[$ext_ref] = $entry;
            } elseif ($code === 'missing') {
                $post[$ext_ref] = $entry;
            }
        }
        
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
        //$pattern = DIR_LOGS . $store_slug . '_mp-import_products-*.*';
        $pattern = DIR_LOGS . $store_slug . '_mp-import_products-*.json';
        $files = glob($pattern);
        if ($files) {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
        
        /* // find latest json patch file (pattern)
        $patchfilepattern = DIR_LOGS . $store_slug . '_mp-import_products-patch_*.json';
        $patchfiles = glob($patchfilepattern);
        if ($patchfiles) {
            usort($patchfiles, function($patchfa, $patchfb) { return filemtime($patchfb) - filemtime($patchfa); });
            $patchfile = $patchfiles[0];
        } else { $patchfile = DIR_LOGS . $store_slug . '_mp-import_products-patch_' . date('Y-m-d') . '.json'; }
        */
        $patchfile      = DIR_LOGS . $store_slug . '_mp-import_products-patch_' . date('Y-m-d') . '.json';
        $postfile       = DIR_LOGS . $store_slug . '_mp-import_products-post_' . date('Y-m-d') . '.json';
        $deletefile     = DIR_LOGS . $store_slug . '_mp-import_products-delete_' . date('Y-m-d') . '.json';
        
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
        
        return array('success' => true, 'error' => false, 'oc' => $out);
        
    }
    
    public function getProductDetailsForMP($ocproduct) {
        // get only products of type simple and variable, the variants are determined
        
        $language_id = (int)$this->config->get('config_language_id');
        $error = '';
        $product = array();
        
        // only simple and variable product types
        if($ocproduct['product_type'] == 'simple') { $product['type'] = 'basic'; }
        elseif($ocproduct['product_type'] == 'variable') { $product['type'] = 'multi_variant'; }
        else {
            // undefined product type is not accepted... 
            return array('success' => false, 'error' => 'Cannot use variant or undefined product type', 'product' => array());
        }
        
        $product['product_id'] = (int)$ocproduct['product_id_base']; // not needed for mp import but needed to get the product details, also preserverd for other needs
        
        $product['id'] = $ocproduct['mp_id']; // if null means POST (new) otherwise is PATCH (update) thus MP ID is needed
        
        // get product name, description, etc...
        $pdsql = $this->db->query(" SELECT p.*, pd.* 
                                        FROM `" . DB_PREFIX . "product` p 
                                            LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id) 
                                        WHERE pd.language_id = '" . $language_id . "' 
                                            AND p.product_id = '" . $product['product_id'] . "' 
        ")->row;
        
        // get product attributes
        $pasql = $this->db->query(" SELECT pa.attribute_id, ad.name, pa.text, pa.filterseo 
                                        FROM " . DB_PREFIX . "product_attribute pa
                                            LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (pa.attribute_id = ad.attribute_id AND ad.language_id = '" . $language_id . "')
                                        WHERE pa.product_id = '" . (int)$product_id . "' 
        ")->rows;
        
        $pattrtable = $this->buildAttributesHtmlTable($pasql);
        
        // get another oc products details... 
        //...
        
        // product details needed as per mp api product
        empty($ocproduct['model']) ? $error = 'oc product model error' : ''; // additional check / may compare with $pdsql ...
        $product['sku'] = $ocproduct['model']; // basic/simple and multi_variant/variable products use oc model (as it is), the variants use oc model with suffix (determined below)
        
        empty($ocproduct['ext_ref']) ? $error = 'oc product ext_ref error' : ''; // additional check / may compare with $pdsql ...
        $product['ext_ref'] = $ocproduct['ext_ref']; // basic/simple and multi_variant/variable products use oc product_id (as it is), the variants use oc product_id with suffix (determined below)
        
        empty($ocproduct['name']) ? $error = 'oc product name error' : ''; // additional check / may compare with $pdsql ...
        $product['name'] = $ocproduct['name']; // basic/simple  and multi_variant/variable products use oc product_name (as it is), maybe the variants may use oc product_name with suffix
        
        $product['description'] = (!empty($pdsql['description']) ? $this->cleanhtml($pdsql['description']) : '');
        $product['description'] .= (!empty($pattrtable) ? '<hr>'.$pattrtable : '');
        $product['description'] .= (!empty($pdsql['specificatii']) ? '<hr>'.$this->cleanhtml($pdsql['specificatii']) : '');
        $product['description'] .= (!empty($pdsql['aplicatii']) ? '<hr>'.$this->cleanhtml($pdsql['aplicatii']) : '');
        
        $product['meta_title'] = $ocproduct['name'];
        $product['meta_description'] = (!empty($pdsql['meta_description']) ? $this->cleantxt($pdsql['meta_description']) : (!empty($pattrtable) ? $this->cleantxt($pattrtable) : '') );
        
        $product['inventory_enabled'] = 'on'; // on or off
        
        $product['stock'] = (!empty($ocproduct['quantity']) ? (float)$ocproduct['quantity'] : 0); // make sure that variants gets its own stock
        $product['stock_reserved'] = 0; // make sure that variants gets its own stock
        
        $product['allow_backorders'] = true;  // bool, true or false
        
        //$product['category_id'] = 0;  // int, primary category ID -> check mp categories and get them...!!!
        //$product['category_name'] = '';  // string, primary category name -> try with oc_category or check mp categories api and get them...!!!
        
        //$product['categories'] = (object)array('id' => 0, 'name' => '');  // object, list of assigned categories (requires Multicategory field enabled) -> try with oc_category or check mp api categories and get them...!!!
        
        //$product['tags'] = (object)array('id' => 0, 'name' => '');  // object, product tags (requires Tags field enabled) -> try with oc_tags or check mp api tags and get them...!!!
        
        $mp_tva = 21; // check and get (float) tax value (tva) from mp api
        $product['price_net'] = ( ( isset($ocproduct['lowest_special']) && $ocproduct['lowest_special'] > 0 ) ? (float)($ocproduct['lowest_special'] / (1 + $mp_tva/100)) : (float)($ocproduct['price'] / (1 + $mp_tva/100)) ); // check and get (int) tva from mp api
        $product['price_gross'] = ( ( isset($ocproduct['lowest_special']) && $ocproduct['lowest_special'] > 0 ) ? (float)$ocproduct['lowest_special'] : (float)$ocproduct['price'] );
        
        $product['old_price_net'] = ( ( isset($ocproduct['lowest_special']) && $ocproduct['lowest_special'] > 0 ) ? (float)($ocproduct['price'] / (1 + $mp_tva/100)) : '');
        $product['old_price_gross'] = ( ( isset($ocproduct['lowest_special']) && $ocproduct['lowest_special'] > 0 ) ? (float)$ocproduct['price'] : '' );
        
        $pcost = 0; // get purchase cost from oc... 
        $product['cost_net'] = ( ( isset($pcost) && $pcost > 0 ) ? (float)($pcost / (1 + $mp_tva/100)) : '');
        $product['cost_gross'] = ( ( isset($pcost) && $pcost > 0 ) ? (float)$pcost : '' );
        
        $mp_taxid = 1; // check and get (int) tax id (tva) from mp api
        $product['tax_id'] = $mp_taxid;
        $product['tax_value'] = $mp_tva;
        $mp_taxname = 'TVA'; // check and get (string) tax name (tva) from mp api
        $product['tax_name'] = $mp_taxname;
        
        $product['quantity_multiplier'] = ( ( isset($pdsql['minimum']) && $pdsql['minimum'] > 1 ) ? (int)$pdsql['minimum'] : ( strpos(strtolower($pdsql['name']), 'banda led') !== false ? 5 : 1 ) );
        
        $mp_unitid = 1;  // check and get (int) unit id (tva) from mp api
        $mp_unittype = 'buc';  // check and get (string) unit type (tva) from mp api
        //$product['unit_id'] = $mp_unitid;
        //$product['unit_id'] = $mp_unittype;
        
        // make sure the weight class is kg (see the woo import tool as example)
        $product['weight'] = ( ( isset($pdsql['weight']) && $pdsql['weight'] > 0 ) ? (float)$pdsql['weight'] : 0 ); // make sure that variants gets its own weight
        
        
        
        // get select-type options (only those with type 'select') to get variants from oc directly (as ony simple and variable product types are used now)
        $select_options = $this->getProductSelectOptions($product['product_id']);
        
        if(empty($select_options) && $product['type'] == 'basic') {
            // basic product for mp
        }
        elseif(!empty($select_options) && $product['type'] == 'multi_variant') {
            // variants of the multi_variant product for mp
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
                            'mpn'               => $mpn,
                            'mpn_base'          => !empty($mpn) ? $mpn . '_' . $suffix : '',
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
        
        return array('success' => true, 'error' => false, 'product' => $product);
        
        
    }
    
    protected function cleanhtml($html) {
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
        $html = str_replace('//led-zone.ro', '//www.led-zone.ro', $html);
        
        // Normalize whitespace
        $html = preg_replace('/ {2,}/', ' ', $html);
        $html = trim($html);
        
        return $html;
    }
    
    protected function cleantxt($string) {
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
    
    private function buildAttributesHtmlTable($attrs) {
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
    
}

?>