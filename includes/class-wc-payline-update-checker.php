<?php
/**
 * Vérificateur de mises à jour via GitHub Releases
 *
 * @since 1.5.12
 */

if (!defined('ABSPATH')) exit;

class WC_Payline_Update_Checker {

    private $slug;
    private $plugin_slug;
    private $version;
    private $cache_key;
    private $github_api_url;
    private $plugin_name;
    private $description;
    private $author;
    private $author_url;
    private $requires_at_least;
    private $requires_php;
    private $tested;

    public function __construct($file, $github_repo, $version)
    {
        $this->slug = plugin_basename($file);
        $this->plugin_slug = dirname($this->slug);
        $this->version = $version;
        $this->cache_key = 'payline_gh_release';
        $this->github_api_url = "https://api.github.com/repos/{$github_repo}/releases/latest";

        $file_data = get_file_data(WP_PLUGIN_DIR . '/' . $file, array(
            'plugin_name' => 'Plugin Name',
            'description' => 'Description',
            'author' => 'Author',
            'author_url' => 'Author URI',
            'requires_at_least' => 'Requires at least',
            'requires_php' => 'Requires PHP',
            'tested' => 'Tested up to',
        ), 'plugin');
        foreach ($file_data as $key => $value) {
            $this->$key = !empty($value) ? $value : null;
        }

        add_filter('plugins_api', array($this, 'info'), 20, 3);
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
    }

    /**
     * Récupère la dernière release GitHub
     *
     * @return object|false
     */
    private function get_release()
    {
        $cached = get_transient($this->cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get($this->github_api_url, array(
            'headers' => array(
                'Accept' => 'application/vnd.github+json',
            ),
            'timeout' => 10,
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $release = json_decode(wp_remote_retrieve_body($response));

        if (empty($release) || isset($release->message)) {
            return false;
        }

        set_transient($this->cache_key, $release, 12 * HOUR_IN_SECONDS);

        return $release;
    }

    /**
     * Vérifie s'il y a une mise à jour disponible et l'injecte dans le transient.
     *
     * @param object $transient
     * @return object
     */
    public function check_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_release();
        if (!$release) {
            return $transient;
        }

        $latest_version = $release->tag_name;

        if (version_compare($this->version, $latest_version, '<')) {
            $download_url = null;

            if (!empty($release->assets) && is_array($release->assets)) {
                foreach ($release->assets as $asset) {
                    if (strpos($asset->name, '.zip') !== false) {
                        $download_url = $asset->browser_download_url;
                        break;
                    }
                }
            }

            if (!$download_url) {
                return $transient;
            }

            $transient->response[$this->slug] = (object)array(
                'slug' => $this->plugin_slug,
                'plugin' => $this->slug,
                'url' => $release->html_url,
                'new_version' => $latest_version,
                'package' => $download_url,
                'tested' => $this->tested,
                'requires_php' => $this->requires_php,
                'icons' => ["1x" => WCPAYLINE_PLUGIN_URL . 'assets/images/icone-monext.svg'],
            );
        } else {
            if (isset($transient->response[$this->slug])) {
                unset($transient->response[$this->slug]);
            }
            $transient->no_update[$this->slug] = (object)array(
                'slug' => $this->plugin_slug,
                'plugin' => $this->slug,
                'new_version' => $this->version,
            );
        }

        return $transient;
    }

    /**
     * Ajoute les informations à la popup "Afficher les détails de la version..."
     *
     * @param $res
     * @param $action
     * @param $args
     * @return mixed|stdClass
     */
    public function info($res, $action, $args)
    {
        if ('plugin_information' !== $action) {
            return $res;
        }

        if ($this->plugin_slug !== $args->slug) {
            return $res;
        }

        $remote = $this->get_release();
        if (!$remote) {
            return $res;
        }

        $res = new stdClass();
        $res->name = $this->plugin_name;
        $res->slug = $this->plugin_slug;
        $res->version = $remote->name;
        $res->tested = $this->tested;
        $res->requires = $this->requires_at_least;
        $res->author = $this->author;
        $res->homepage = $this->author_url;
        $res->download_link = "https://github.com/Monext/monext-woocommerce/releases/download/1.5.10/woocommerce-payline_v1.5.10.zip";
        $res->requires_php = $this->requires_php;
        $res->sections = array(
            'description' => $this->description,
            'changelog' => nl2br($remote->body)
        );

        return $res;
    }
}