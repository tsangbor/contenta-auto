<?php
/**
 * 步驟 12: JSON 模板圖片路徑替換
 * 透過 image-mapping.json 替換所有 JSON 模板中的圖片路徑
 */

// 載入 ContentResolver 類別
require_once DEPLOY_BASE_PATH . '/includes/class-content-resolver.php';

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
$processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
$domain = $processed_data['confirmed_data']['domain'];

$deployer->log("開始執行步驟 12: JSON 模板圖片路徑替換");

try {
    // 1. 載入圖片映射資料
    $image_mapping_file = $work_dir . '/image-mapping.json';
    if (!file_exists($image_mapping_file)) {
        throw new Exception("圖片映射檔案不存在: $image_mapping_file");
    }
    
    $image_mapping = json_decode(file_get_contents($image_mapping_file), true);
    if (empty($image_mapping)) {
        throw new Exception("圖片映射資料為空或無效");
    }
    
    $deployer->log("載入圖片映射資料，共 " . count($image_mapping) . " 個映射");
    
    // 2. 處理頁面模板 (-ai.json 檔案)
    $deployer->log("開始處理頁面模板檔案");
    $layout_dir = $work_dir . '/layout';
    $page_templates = glob($layout_dir . '/*-ai.json');
    $page_updated = 0;
    
    foreach ($page_templates as $template_file) {
        $template_name = basename($template_file, '-ai.json');
        $deployer->log("處理頁面模板: $template_name");
        
        if (updateJsonTemplateImagePaths($template_file, $image_mapping, $template_name, $work_dir, $deployer)) {
            $page_updated++;
        }
    }
    
    // 3. 處理全域模板 (global/*-ai.json 檔案)
    $deployer->log("開始處理全域模板檔案");
    $global_dir = $work_dir . '/layout/global';
    $global_updated = 0;
    
    if (is_dir($global_dir)) {
        $global_templates = glob($global_dir . '/*-ai.json');
        
        foreach ($global_templates as $template_file) {
            $template_name = basename($template_file, '-ai.json');
            $deployer->log("處理全域模板: $template_name");
            
            if (updateJsonTemplateImagePaths($template_file, $image_mapping, $template_name, $work_dir, $deployer)) {
                $global_updated++;
            }
        }
    } else {
        $deployer->log("全域模板目錄不存在: $global_dir");
    }
    
    // 4. 處理網站配置檔案
    $deployer->log("開始處理網站配置檔案");
    $site_config_file = $work_dir . '/json/site-config.json';
    $config_updated = 0;
    
    if (file_exists($site_config_file)) {
        if (updateSiteConfigImagePaths($site_config_file, $image_mapping, $deployer)) {
            $config_updated = 1;
            $deployer->log("✅ 更新網站配置檔案成功");
        }
    } else {
        $deployer->log("網站配置檔案不存在: $site_config_file");
    }
    
    // 5. 統計與報告
    $total_updated = $page_updated + $global_updated + $config_updated;
    $deployer->log("圖片路徑替換完成統計:");
    $deployer->log("  - 頁面模板: $page_updated 個");
    $deployer->log("  - 全域模板: $global_updated 個");
    $deployer->log("  - 網站配置: $config_updated 個");
    $deployer->log("  - 總計: $total_updated 個檔案已更新");

    // 6. 儲存步驟結果
    $step_result = [
        'step' => '12',
        'title' => 'JSON 模板圖片路徑替換',
        'status' => 'success',
        'message' => "成功更新 $total_updated 個檔案的圖片路徑",
        'page_templates_updated' => $page_updated,
        'global_templates_updated' => $global_updated,
        'config_files_updated' => $config_updated,
        'total_updated' => $total_updated,
        'image_mappings_used' => count($image_mapping),
        'executed_at' => date('Y-m-d H:i:s')
    ];

    file_put_contents($work_dir . '/step-12-result.json', json_encode($step_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $deployer->log("步驟 12: JSON 模板圖片路徑替換 - 完成");

    return ['status' => 'success', 'result' => $step_result];

} catch (Exception $e) {
    $deployer->log("步驟 12 執行失敗: " . $e->getMessage());
    return ['status' => 'error', 'message' => $e->getMessage()];
}

/**
 * 更新 JSON 模板檔案中的圖片路徑
 */
function updateJsonTemplateImagePaths($json_file, $image_mapping, $template_name, $work_dir, $deployer)
{
    if (!file_exists($json_file)) {
        $deployer->log("  檔案不存在: $json_file");
        return false;
    }

    $json_content = file_get_contents($json_file);
    $original_content = $json_content;
    $replacement_count = 0;

    // 檢查是否為全域模板
    $is_global_template = strpos($json_file, '/global/') !== false;

    if ($is_global_template) {
        // 全域模板處理：處理 {{PLACEHOLDER}} 格式的佔位符
        if (preg_match_all('/\{\{([^}]+)\}\}/', $json_content, $matches)) {
            foreach ($matches[1] as $placeholder) {
                $deployer->log("    發現全域模板佔位符: {{$placeholder}} (原始: $placeholder)");
                
                // 檢查是否為圖片佔位符
                if (preg_match('/(BG|IMAGE|PHOTO|LOGO|ICON)/', $placeholder)) {
                    // 圖片佔位符：使用分組圖片映射結構查找
                    $image_replaced = false;
                    
                    // 先檢查當前模板名稱的圖片映射
                    if (isset($image_mapping[$template_name]) && isset($image_mapping[$template_name][$placeholder])) {
                        // 適應新的資料結構
                        $image_data = $image_mapping[$template_name][$placeholder];
                        $wp_url = is_array($image_data) ? $image_data['url'] : $image_data;
                        $attachment_id = is_array($image_data) ? $image_data['attachment_id'] : null;
                        
                        // 使用新的智能替換函數同時處理 URL 和 ID
                        $json_content = replaceImagePlaceholderWithId($json_content, $placeholder, $wp_url, $attachment_id, $deployer);
                        $replacement_count++;
                        $deployer->log("    ✅ 替換全域圖片佔位符: {{$placeholder}} -> $wp_url" . ($attachment_id ? " (ID: $attachment_id)" : "") . " (模板: $template_name)");
                        $image_replaced = true;
                    } else {
                        // 如果當前模板沒有，搜尋所有頁面的映射
                        foreach ($image_mapping as $page_name => $page_images) {
                            if (isset($page_images[$placeholder])) {
                                // 適應新的資料結構
                                $image_data = $page_images[$placeholder];
                                $wp_url = is_array($image_data) ? $image_data['url'] : $image_data;
                                $attachment_id = is_array($image_data) ? $image_data['attachment_id'] : null;
                                
                                // 使用新的智能替換函數同時處理 URL 和 ID
                                $json_content = replaceImagePlaceholderWithId($json_content, $placeholder, $wp_url, $attachment_id, $deployer);
                                $replacement_count++;
                                $deployer->log("    ✅ 替換全域圖片佔位符: {{$placeholder}} -> $wp_url" . ($attachment_id ? " (ID: $attachment_id)" : "") . " (來源頁面: $page_name)");
                                $image_replaced = true;
                                break;
                            }
                        }
                    }
                    
                    if (!$image_replaced) {
                        $deployer->log("    ⚠️ 未找到全域圖片映射: {{$placeholder}}");
                    }
                } else {
                    // 文字佔位符：使用新的多層次內容解析器
                    $deployer->log("    處理文字佔位符: $placeholder");
                    
                    // 初始化 ContentResolver
                    $resolver = new ContentResolver($deployer);
                    
                    // 準備上下文資料
                    $processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
                    $context = [
                        'confirmed_data' => $processed_data['confirmed_data'] ?? [],
                        'template_name' => $template_name,
                        'placeholder' => $placeholder
                    ];
                    
                    // 解析佔位符內容
                    $replaced_text = $resolver->resolve_placeholder_content($placeholder, $context);
                    
                    if (!empty($replaced_text) && $replaced_text !== "內容生成中...") {
                        $json_content = str_replace('{{' . $placeholder . '}}', $replaced_text, $json_content);
                        $replacement_count++;
                        $deployer->log("    ✅ 成功替換全域文字佔位符: {{$placeholder}} -> $replaced_text");
                    } else {
                        $deployer->log("    ⚠️ 使用備用值替換: {{$placeholder}} -> $replaced_text");
                        $json_content = str_replace('{{' . $placeholder . '}}', $replaced_text, $json_content);
                        $replacement_count++;
                    }
                    
                    // 記錄 AI 使用統計
                    if ($resolver->get_ai_call_count() > 0) {
                        $deployer->log("    💰 AI 使用統計 - 本次處理呼叫次數: " . $resolver->get_ai_call_count());
                    }
                }
            }
        }
        
        // 額外處理：直接尋找任何圖片檔案並嘗試匹配
        if (preg_match_all('/"url":\s*"([^"]+\.(jpg|jpeg|png|gif|webp|svg|bmp|tiff|avif))"/', $json_content, $matches)) {
            foreach ($matches[1] as $found_filename) {
                $deployer->log("    發現圖片檔案: $found_filename");
                
                // 嘗試找到對應的映射（使用分組結構）
                foreach ($image_mapping as $page_name => $page_images) {
                    foreach ($page_images as $image_key => $image_data) {
                        // 適應新的資料結構 
                        $wp_url = is_array($image_data) ? $image_data['url'] : $image_data;
                        $attachment_id = is_array($image_data) ? $image_data['attachment_id'] : null;
                        $found_match = false;
                        
                        // 根據全域模板類型選擇圖片
                        if (strpos($template_name, 'footer') !== false) {
                            // footer 模板使用任何包含 footer 的圖片，或者當前模板名稱匹配
                            if (strpos($page_name, 'footer') !== false || $page_name === $template_name) {
                                $found_match = true;
                            }
                        } elseif (strpos($template_name, 'header') !== false) {
                            // header 模板使用任何包含 header 的圖片
                            if (strpos($page_name, 'header') !== false || $page_name === $template_name) {
                                $found_match = true;
                            }
                        } elseif (strpos($template_name, 'archive') !== false) {
                            // archive 模板使用任何包含 archive 的圖片
                            if (strpos($page_name, 'archive') !== false || $page_name === $template_name) {
                                $found_match = true;
                            }
                        }
                        
                        if ($found_match) {
                            $json_content = str_replace($found_filename, $wp_url, $json_content);
                            $replacement_count++;
                            $deployer->log("    全域模板替換: $found_filename -> $wp_url (來源: $page_name.$image_key)");
                            break 2; // 跳出兩層迴圈
                        }
                    }
                }
            }
        }
    } else {
        // 頁面模板處理：處理 {{PLACEHOLDER}} 格式
        if (preg_match_all('/\{\{([^}]+)\}\}/', $json_content, $matches)) {
            foreach ($matches[1] as $placeholder) {
                $deployer->log("    發現佔位符: {{$placeholder}}");
                
                // 檢查是否為圖片佔位符
                if (preg_match('/(BG|IMAGE|PHOTO|LOGO|ICON)/', $placeholder)) {
                    // 圖片佔位符：使用圖片映射
                    // 使用分組圖片映射結構查找
                    $image_replaced = false;
                    
                    // 先檢查當前頁面的圖片映射
                    if (isset($image_mapping[$template_name]) && isset($image_mapping[$template_name][$placeholder])) {
                        // 適應新的資料結構
                        $image_data = $image_mapping[$template_name][$placeholder];
                        $wp_url = is_array($image_data) ? $image_data['url'] : $image_data;
                        $attachment_id = is_array($image_data) ? $image_data['attachment_id'] : null;
                        
                        // 使用新的智能替換函數同時處理 URL 和 ID
                        $json_content = replaceImagePlaceholderWithId($json_content, $placeholder, $wp_url, $attachment_id, $deployer);
                        $replacement_count++;
                        $deployer->log("    ✅ 替換圖片佔位符: {{$placeholder}} -> $wp_url" . ($attachment_id ? " (ID: $attachment_id)" : "") . " (頁面: $template_name)");
                        $image_replaced = true;
                    } else {
                        // 如果當前頁面沒有，搜尋其他頁面的映射
                        foreach ($image_mapping as $page_name => $page_images) {
                            if (isset($page_images[$placeholder])) {
                                // 適應新的資料結構
                                $image_data = $page_images[$placeholder];
                                $wp_url = is_array($image_data) ? $image_data['url'] : $image_data;
                                $attachment_id = is_array($image_data) ? $image_data['attachment_id'] : null;
                                
                                // 使用新的智能替換函數同時處理 URL 和 ID
                                $json_content = replaceImagePlaceholderWithId($json_content, $placeholder, $wp_url, $attachment_id, $deployer);
                                $replacement_count++;
                                $deployer->log("    ✅ 替換圖片佔位符: {{$placeholder}} -> $wp_url" . ($attachment_id ? " (ID: $attachment_id)" : "") . " (來源頁面: $page_name)");
                                $image_replaced = true;
                                break;
                            }
                        }
                    }
                    
                    // 如果圖片映射失敗，不進行文字替換
                    if (!$image_replaced) {
                        $deployer->log("    ⚠️ 未找到圖片映射: {{$placeholder}}");
                    }
                } else {
                    // 文字佔位符：使用 ContentResolver
                    $resolver = new ContentResolver($deployer);
                    
                    // 準備上下文資料
                    $processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
                    $context = [
                        'confirmed_data' => $processed_data['confirmed_data'] ?? [],
                        'template_name' => $template_name,
                        'placeholder' => $placeholder
                    ];
                    
                    // 解析佔位符內容
                    $replaced_text = $resolver->resolve_placeholder_content($placeholder, $context);
                    
                    $json_content = str_replace('{{' . $placeholder . '}}', $replaced_text, $json_content);
                    $replacement_count++;
                    $deployer->log("    ✅ 替換文字佔位符: {{$placeholder}} -> $replaced_text");
                    
                    // 記錄 AI 使用統計
                    if ($resolver->get_ai_call_count() > 0) {
                        $deployer->log("    💰 AI 使用統計 - 本次處理呼叫次數: " . $resolver->get_ai_call_count());
                    }
                }
            }
        }
        
        // 另外處理直接的圖片檔案路徑格式
        foreach ($image_mapping as $page_name => $page_images) {
            foreach ($page_images as $image_key => $image_data) {
                // 適應新的資料結構
                $wp_url = is_array($image_data) ? $image_data['url'] : $image_data;
                $attachment_id = is_array($image_data) ? $image_data['attachment_id'] : null;
                
                // 檢查是否包含模板名稱前綴
                if (strpos($image_key, $template_name . '_') === 0) {
                    // 移除前綴獲得原始圖片名稱
                    $original_name = substr($image_key, strlen($template_name . '_'));
                
                    // 各種可能的路徑格式
                    $search_patterns = [
                        "/wp-content/uploads/2025/06/$original_name.jpg",
                        "/wp-content/uploads/2025/06/$original_name.png",
                        "/wp-content/uploads/2025/06/$original_name.webp",
                        "/wp-content/uploads/ai-generated/$original_name.png",
                        "/wp-content/uploads/ai-generated/$original_name.jpg",
                        "/wp-content/uploads/ai-generated/$original_name.webp",
                        $original_name . ".jpg",
                        $original_name . ".png",
                        $original_name . ".webp",
                        $original_name . ".gif",
                        $original_name . ".svg"
                    ];

                    foreach ($search_patterns as $pattern) {
                        if (strpos($json_content, $pattern) !== false) {
                            $json_content = str_replace($pattern, $wp_url, $json_content);
                            $replacement_count++;
                            $deployer->log("    替換: $pattern -> $wp_url");
                        }
                    }
                }
            }
        }
    }

    // 如果有更新，寫回檔案
    if ($json_content !== $original_content) {
        file_put_contents($json_file, $json_content);
        $deployer->log("  ✅ 完成 $replacement_count 個路徑替換");
        return true;
    } else {
        $deployer->log("  📋 沒有需要替換的圖片路徑");
        return false;
    }
}

/**
 * 更新網站配置檔案中的圖片路徑
 */
function updateSiteConfigImagePaths($config_file, $image_mapping, $deployer)
{
    if (!file_exists($config_file)) {
        return false;
    }

    $config_data = json_decode(file_get_contents($config_file), true);
    if (!$config_data) {
        $deployer->log("  無法解析配置檔案 JSON");
        return false;
    }

    $updated = false;
    $replacement_count = 0;

    // 遞歸替換配置中的圖片路徑
    $config_data = replaceImagePathsInArray($config_data, $image_mapping, $replacement_count, $deployer);

    if ($replacement_count > 0) {
        file_put_contents($config_file, json_encode($config_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $deployer->log("  ✅ 配置檔案完成 $replacement_count 個路徑替換");
        $updated = true;
    } else {
        $deployer->log("  📋 配置檔案沒有需要替換的圖片路徑");
    }

    return $updated;
}

// 舊的 getGlobalTemplateTextReplacement 函數已被 ContentResolver 取代

/**
 * 遞歸替換陣列中的圖片路徑
 */
function replaceImagePathsInArray($data, $image_mapping, &$replacement_count, $deployer)
{
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // 檢查是否為圖片路徑
                if (preg_match('/\/wp-content\/uploads\/.*\.(jpg|jpeg|png|gif|webp|svg|bmp|tiff|avif)/', $value)) {
                    // 嘗試找到對應的映射（使用分組結構）
                    foreach ($image_mapping as $page_name => $page_images) {
                        foreach ($page_images as $image_key => $image_data) {
                            // 適應新的資料結構
                            $wp_url = is_array($image_data) ? $image_data['url'] : $image_data;
                            $attachment_id = is_array($image_data) ? $image_data['attachment_id'] : null;
                            
                            // 從路徑中提取檔名部分進行匹配
                            $path_filename = pathinfo($value, PATHINFO_FILENAME);
                            $image_filename = pathinfo($wp_url, PATHINFO_FILENAME);
                            
                            // 嘗試多種匹配方式
                            if (strpos($value, $image_key) !== false || 
                                strpos($path_filename, $image_key) !== false ||
                                strpos($path_filename, strtolower(str_replace('_', '-', $image_key))) !== false) {
                                $data[$key] = $wp_url;
                                $replacement_count++;
                                $deployer->log("    配置替換: $value -> $wp_url (來源: $page_name.$image_key)");
                                break 2; // 跳出兩層迴圈
                            }
                        }
                    }
                }
            } elseif (is_array($value)) {
                // 檢查是否為 Elementor 圖片對象
                if (isElementorImageObject($value)) {
                    $updated_image = replaceElementorImageObject($value, $image_mapping, $replacement_count, $deployer);
                    if ($updated_image !== $value) {
                        $data[$key] = $updated_image;
                    }
                } else {
                    $data[$key] = replaceImagePathsInArray($value, $image_mapping, $replacement_count, $deployer);
                }
            }
        }
    }

    return $data;
}

/**
 * 檢查是否為 Elementor 圖片對象
 */
function isElementorImageObject($data) {
    return is_array($data) && 
           isset($data['url']) && 
           isset($data['id']) && 
           is_string($data['url']) && 
           (is_int($data['id']) || is_numeric($data['id'])) &&
           preg_match('/\.(jpg|jpeg|png|gif|webp|svg|bmp|tiff|avif)$/i', $data['url']);
}

/**
 * 替換 Elementor 圖片對象的 URL 和 ID
 */
function replaceElementorImageObject($image_object, $image_mapping, &$replacement_count, $deployer) {
    $original_url = $image_object['url'];
    $original_id = $image_object['id'];
    
    // 搜尋對應的映射
    foreach ($image_mapping as $page_name => $page_images) {
        foreach ($page_images as $image_key => $image_data) {
            // 適應新的資料結構
            $wp_url = is_array($image_data) ? $image_data['url'] : $image_data;
            $attachment_id = is_array($image_data) ? $image_data['attachment_id'] : null;
            
            // 多種匹配方式
            $original_filename = pathinfo($original_url, PATHINFO_FILENAME);
            $wp_filename = pathinfo($wp_url, PATHINFO_FILENAME);
            
            if (
                // 直接 URL 匹配
                strpos($original_url, $wp_url) !== false ||
                strpos($wp_url, $original_filename) !== false ||
                // 根據 image_key 匹配
                strpos($original_url, $image_key) !== false ||
                strpos($original_filename, $image_key) !== false ||
                // 檔名匹配
                $original_filename === $wp_filename ||
                // 模糊匹配（移除特殊字符後比較）
                preg_replace('/[^a-zA-Z0-9]/', '', strtolower($original_filename)) === 
                preg_replace('/[^a-zA-Z0-9]/', '', strtolower($wp_filename))
            ) {
                // 找到匹配，更新圖片對象
                $updated_object = $image_object;
                $updated_object['url'] = $wp_url;
                
                if ($attachment_id !== null) {
                    $updated_object['id'] = intval($attachment_id);
                }
                
                $replacement_count++;
                $deployer->log("    🖼️ Elementor 圖片對象替換: ");
                $deployer->log("      URL: $original_url -> $wp_url");
                $deployer->log("      ID:  $original_id -> " . ($attachment_id ?? '保持原有'));
                $deployer->log("      匹配來源: $page_name.$image_key");
                
                return $updated_object;
            }
        }
    }
    
    // 沒有找到匹配，返回原始對象
    return $image_object;
}

/**
 * 智能替換圖片佔位符，同時處理 URL 和對應的 ID
 */
function replaceImagePlaceholderWithId($json_content, $placeholder, $wp_url, $attachment_id, $deployer) {
    $placeholder_pattern = '{{' . $placeholder . '}}';
    $updated_content = $json_content;
    
    // 如果有 attachment_id，先處理 ID 替換（在 URL 替換之前）
    if ($attachment_id !== null) {
        // 查找包含該佔位符的圖片對象模式
        // 支援多種圖片欄位名稱: image, background_image, photo 等
        $image_field_names = ['image', 'background_image', 'photo', 'logo', 'icon'];
        $replaced = false;
        
        foreach ($image_field_names as $field_name) {
            if ($replaced) break;
            
            // 查找包含佔位符的圖片對象模式
            $pattern = '/"' . $field_name . '"\s*:\s*\{[^}]*"url"\s*:\s*"' . preg_quote($placeholder_pattern, '/') . '"[^}]*\}/';
            
            if (preg_match($pattern, $updated_content, $matches)) {
                $old_image_object = $matches[0];
                
                // 在匹配到的圖片對象內查找並替換 ID
                if (preg_match('/"id"\s*:\s*\d+/', $old_image_object)) {
                    $new_image_object = preg_replace('/"id"\s*:\s*\d+/', '"id":' . intval($attachment_id), $old_image_object);
                    $updated_content = str_replace($old_image_object, $new_image_object, $updated_content);
                    
                    $deployer->log("      🔄 同時替換圖片 ID: $attachment_id (" . $field_name . "格式)");
                    $replaced = true;
                } else {
                    // 如果圖片對象中沒有 ID 欄位，需要添加 ID
                    // 在 URL 欄位後面添加 ID
                    $new_image_object = preg_replace(
                        '/("url"\s*:\s*"' . preg_quote($placeholder_pattern, '/') . '")/',
                        '$1,"id":' . intval($attachment_id),
                        $old_image_object
                    );
                    $updated_content = str_replace($old_image_object, $new_image_object, $updated_content);
                    
                    $deployer->log("      🔄 添加圖片 ID: $attachment_id (" . $field_name . "格式)");
                    $replaced = true;
                }
            }
        }
        
        if (!$replaced) {
            // 嘗試通用的圖片對象格式 (沒有欄位名稱前綴)
            $general_pattern = '/\{[^}]*"url"\s*:\s*"' . preg_quote($placeholder_pattern, '/') . '"[^}]*\}/';
            
            if (preg_match($general_pattern, $updated_content, $general_matches)) {
                $old_image_object = $general_matches[0];
                
                // 在匹配到的圖片對象內查找並替換 ID
                if (preg_match('/"id"\s*:\s*\d+/', $old_image_object)) {
                    $new_image_object = preg_replace('/"id"\s*:\s*\d+/', '"id":' . intval($attachment_id), $old_image_object);
                    $updated_content = str_replace($old_image_object, $new_image_object, $updated_content);
                    
                    $deployer->log("      🔄 同時替換圖片 ID: $attachment_id (通用格式)");
                    $replaced = true;
                } else {
                    // 如果圖片對象中沒有 ID 欄位，需要添加 ID
                    $new_image_object = preg_replace(
                        '/("url"\s*:\s*"' . preg_quote($placeholder_pattern, '/') . '")/',
                        '$1,"id":' . intval($attachment_id),
                        $old_image_object
                    );
                    $updated_content = str_replace($old_image_object, $new_image_object, $updated_content);
                    
                    $deployer->log("      🔄 添加圖片 ID: $attachment_id (通用格式)");
                    $replaced = true;
                }
            }
            
            if (!$replaced) {
                // 如果沒有找到完整的圖片對象，嘗試在附近查找並替換 id
                // 這是一個更寬鬆的匹配，查找 URL 前後的 id 欄位
                $lines = explode("\n", $updated_content);
                $url_line_index = -1;
                
                // 找到包含 URL 的行
                for ($i = 0; $i < count($lines); $i++) {
                    if (strpos($lines[$i], '"url":"' . $wp_url . '"') !== false) {
                        $url_line_index = $i;
                        break;
                    }
                }
                
                if ($url_line_index !== -1) {
                    // 在 URL 行的前後幾行查找 id 欄位
                    $search_range = 5; // 搜索前後 5 行
                    for ($i = max(0, $url_line_index - $search_range); $i <= min(count($lines) - 1, $url_line_index + $search_range); $i++) {
                        if (preg_match('/"id"\s*:\s*\d+/', $lines[$i])) {
                            $lines[$i] = preg_replace('/"id"\s*:\s*\d+/', '"id":' . intval($attachment_id), $lines[$i]);
                            $updated_content = implode("\n", $lines);
                            $deployer->log("      🔄 鄰近替換圖片 ID: $attachment_id");
                            break;
                        }
                    }
                }
            }
        }
    }
    
    // 最後進行 URL 替換
    $updated_content = str_replace($placeholder_pattern, $wp_url, $updated_content);
    
    return $updated_content;
}