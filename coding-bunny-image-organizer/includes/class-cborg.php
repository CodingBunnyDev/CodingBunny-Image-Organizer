<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/taxonomy.php';
require_once __DIR__ . '/ajax.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/helpers.php';

final class CodingBunny_Image_Organizer {
    use CBORG_Taxonomy;
    use CBORG_Ajax;
    use CBORG_Admin;
    use CBORG_Helpers;

    public const VERSION = '1.2.1';
    public const PLUGIN_DIR = __DIR__;
    public const PLUGIN_URL = __DIR__;
    public const UNCATEGORIZED_SLUG = 'uncategorized';
    public const UNCATEGORIZED_NAME = 'Uncategorized';
    public const PROTECTED_FILTER_SLUG = 'protected';
    private static ?self $instance = null;

    public static function instance(): self {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->hooks();
    }

    private function define_constants(): void {
        if(!defined('CBORG_VERSION')) define('CBORG_VERSION', self::VERSION);
        if(!defined('CBORG_PLUGIN_DIR')) define('CBORG_PLUGIN_DIR', untrailingslashit(dirname(__DIR__)));
        if(!defined('CBORG_PLUGIN_URL')) define('CBORG_PLUGIN_URL', untrailingslashit(plugins_url('', dirname(__DIR__))));
        if(!defined('CBORG_UNCATEGORIZED_SLUG')) define('CBORG_UNCATEGORIZED_SLUG', self::UNCATEGORIZED_SLUG);
        if(!defined('CBORG_UNCATEGORIZED_NAME')) define('CBORG_UNCATEGORIZED_NAME', self::UNCATEGORIZED_NAME);
        if(!defined('CBORG_PROTECTED_FILTER_SLUG')) define('CBORG_PROTECTED_FILTER_SLUG', self::PROTECTED_FILTER_SLUG);
    }

    private function includes(): void {
    }

    private function hooks(): void {
        $this->register_all_hooks();
        $this->register_ajax_hooks();
        $this->register_admin_hooks();
    }
}