<?php
/**
 * SSH 連線輔助工具類別
 *
 * 提供 SSH 相關的共用功能：
 * - 動態偵測使用者 home 目錄（跨平台相容）
 * - 自動搜尋 SSH key
 * - ~ 符號展開
 */

class SSHHelper
{
    /**
     * 取得使用者 home 目錄（跨平台相容）
     *
     * @return string home 目錄路徑
     */
    public static function getHomeDir(): string
    {
        // 方法 1: 環境變數 HOME (Unix/Mac/Linux)
        $home = getenv('HOME');
        if (!empty($home)) {
            return $home;
        }

        // 方法 2: 環境變數 USERPROFILE (Windows)
        $home = getenv('USERPROFILE');
        if (!empty($home)) {
            return $home;
        }

        // 方法 3: $_SERVER['HOME']
        if (!empty($_SERVER['HOME'])) {
            return $_SERVER['HOME'];
        }

        // 方法 4: posix 函數 (Unix/Mac/Linux)
        if (function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
            $user_info = posix_getpwuid(posix_getuid());
            if (!empty($user_info['dir'])) {
                return $user_info['dir'];
            }
        }

        // 方法 5: 嘗試從 HOME 環境組合 (Windows)
        $drive = getenv('HOMEDRIVE');
        $path = getenv('HOMEPATH');
        if (!empty($drive) && !empty($path)) {
            return $drive . $path;
        }

        return '';
    }

    /**
     * 展開路徑中的 ~ 符號為使用者 home 目錄
     *
     * @param string $path 原始路徑
     * @return string 展開後的路徑
     */
    public static function expandTilde(string $path): string
    {
        if (strpos($path, '~') === 0) {
            $home = self::getHomeDir();
            return $home . substr($path, 1);
        }
        return $path;
    }

    /**
     * 自動偵測可用的 SSH key
     *
     * 搜尋順序：id_ed25519 > id_rsa > id_ecdsa
     *
     * @param string|null $configured_path 配置檔案中指定的路徑（可為空）
     * @return array ['path' => string, 'type' => string, 'auto_detected' => bool]
     */
    public static function detectSSHKey(?string $configured_path = null): array
    {
        // 若有配置指定的路徑，先嘗試使用
        if (!empty($configured_path)) {
            $expanded_path = self::expandTilde($configured_path);
            if (file_exists($expanded_path)) {
                return [
                    'path' => $expanded_path,
                    'type' => self::getKeyType($expanded_path),
                    'auto_detected' => false
                ];
            }
        }

        // 自動搜尋常見的 SSH key（依安全性和效能優先順序）
        $key_types = [
            'id_ed25519' => 'Ed25519',
            'id_rsa' => 'RSA',
            'id_ecdsa' => 'ECDSA'
        ];

        $home = self::getHomeDir();
        foreach ($key_types as $filename => $type) {
            $key_path = $home . '/.ssh/' . $filename;
            if (file_exists($key_path)) {
                return [
                    'path' => $key_path,
                    'type' => $type,
                    'auto_detected' => true
                ];
            }
        }

        // 找不到任何 key
        return [
            'path' => '',
            'type' => '',
            'auto_detected' => false
        ];
    }

    /**
     * 根據檔名判斷 key 類型
     *
     * @param string $path SSH key 路徑
     * @return string key 類型
     */
    private static function getKeyType(string $path): string
    {
        $filename = basename($path);
        if (strpos($filename, 'ed25519') !== false) {
            return 'Ed25519';
        } elseif (strpos($filename, 'ecdsa') !== false) {
            return 'ECDSA';
        } elseif (strpos($filename, 'rsa') !== false) {
            return 'RSA';
        }
        return 'Unknown';
    }

    /**
     * 建立 SSH 命令
     *
     * @param string $host 主機位址
     * @param string $user 使用者名稱
     * @param int $port SSH 連接埠
     * @param string $key_path SSH key 路徑
     * @param string $command 要執行的命令
     * @return string 完整的 SSH 命令
     */
    public static function buildSSHCommand(
        string $host,
        string $user,
        int $port,
        string $key_path,
        string $command
    ): string {
        $ssh_cmd = "ssh";

        if (!empty($key_path) && file_exists($key_path)) {
            $ssh_cmd .= " -i " . escapeshellarg($key_path);
        }

        if ($port !== 22) {
            $ssh_cmd .= " -p {$port}";
        }

        $ssh_cmd .= " -o StrictHostKeyChecking=no";
        $ssh_cmd .= " -o ConnectTimeout=30";
        $ssh_cmd .= " {$user}@{$host}";
        $ssh_cmd .= " " . escapeshellarg($command);

        return $ssh_cmd;
    }

    /**
     * 執行 SSH 命令
     *
     * @param string $host 主機位址
     * @param string $user 使用者名稱
     * @param int $port SSH 連接埠
     * @param string $key_path SSH key 路徑
     * @param string $command 要執行的命令
     * @return array ['return_code' => int, 'output' => string, 'command' => string]
     */
    public static function executeSSH(
        string $host,
        string $user,
        int $port,
        string $key_path,
        string $command
    ): array {
        $ssh_cmd = self::buildSSHCommand($host, $user, $port, $key_path, $command);

        $output = [];
        $return_code = 0;

        exec($ssh_cmd . ' 2>&1', $output, $return_code);

        return [
            'return_code' => $return_code,
            'output' => implode("\n", $output),
            'command' => $command
        ];
    }
}
