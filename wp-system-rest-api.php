<?php
/**
 * Plugin Name: WP System REST API
 * Plugin URI: https://github.com/brunoalbim/wp-system-rest-api
 * Description: Expõe informações do sistema WordPress via REST API protegida por autenticação. Inclui integração com UpdraftPlus para visibilidade de backups.
 * Version: 0.4.1
 * Author: Bruno Albim
 * Author URI: https://github.com/brunoalbim
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-system-rest-api
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Update URI: https://github.com/brunoalbim/wp-system-rest-api
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class WP_System_REST_API {

    const API_NAMESPACE = 'wp-system/v1';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_action( 'admin_init', array( $this, 'register_update_checker' ) );
    }

    public function register_update_checker() {
        $update_checker = PucFactory::buildUpdateChecker(
            'https://github.com/brunoalbim/wp-system-rest-api/',
            __FILE__,
            'wp-system-rest-api'
        );

        $update_checker->getVcsApi()->enableReleaseAssets();
    }

    public function register_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/info',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'handle_system_info_request' ),
                'permission_callback' => array( $this, 'check_permission' ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/backup',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'handle_backup_request' ),
                'permission_callback' => array( $this, 'check_permission' ),
            )
        );
    }

    public function check_permission() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'Você precisa estar autenticado para acessar este endpoint.', 'wp-system-rest-api' ),
                array( 'status' => 401 )
            );
        }

        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'Você não tem permissão para acessar este recurso.', 'wp-system-rest-api' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Endpoint /info (existente)
    // -------------------------------------------------------------------------

    public function handle_system_info_request( $request ) {
        try {
            $system_data = array(
                'wordpress_version' => $this->get_wordpress_version(),
                'php_version'       => $this->get_php_version(),
                'theme'             => $this->get_theme_info(),
                'plugins'           => $this->get_plugins_info(),
            );

            $system_data['timestamp']     = current_time( 'mysql' );
            $system_data['timestamp_gmt'] = current_time( 'mysql', true );

            return rest_ensure_response( $system_data );

        } catch ( Exception $e ) {
            return new WP_Error(
                'system_info_error',
                sprintf( __( 'Erro ao coletar informações do sistema: %s', 'wp-system-rest-api' ), $e->getMessage() ),
                array( 'status' => 500 )
            );
        }
    }

    private function get_wordpress_version() {
        $current_version = get_bloginfo( 'version' );
        $update_core     = get_site_transient( 'update_core' );

        $update_available = false;
        $latest_version   = $current_version;

        if ( $update_core && ! empty( $update_core->updates ) ) {
            foreach ( $update_core->updates as $update ) {
                if ( $update->response === 'upgrade' ) {
                    $update_available = true;
                    $latest_version   = $update->version;
                    break;
                }
            }
        }

        return array(
            'version'          => $current_version,
            'update_available' => $update_available,
            'latest_version'   => $latest_version,
        );
    }

    private function get_php_version() {
        return phpversion();
    }

    private function get_theme_info() {
        $theme         = wp_get_theme();
        $theme_updates = get_site_transient( 'update_themes' );
        $stylesheet    = get_stylesheet();

        $update_available = false;
        $latest_version   = $theme->get( 'Version' );

        if ( isset( $theme_updates->response[ $stylesheet ] ) ) {
            $update_available = true;
            $latest_version   = $theme_updates->response[ $stylesheet ]['new_version'];
        }

        return array(
            'name'             => $theme->get( 'Name' ),
            'version'          => $theme->get( 'Version' ),
            'update_available' => $update_available,
            'latest_version'   => $latest_version,
            'author'           => $theme->get( 'Author' ),
            'template'         => $theme->get_template(),
            'stylesheet'       => $theme->get_stylesheet(),
        );
    }

    private function get_plugins_info() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins    = get_plugins();
        $plugin_updates = get_site_transient( 'update_plugins' );
        $plugins_data   = array();

        foreach ( $all_plugins as $plugin_path => $plugin_info ) {
            $is_active        = is_plugin_active( $plugin_path );
            $update_available = false;
            $latest_version   = $plugin_info['Version'];

            if ( isset( $plugin_updates->response[ $plugin_path ] ) ) {
                $update_available = true;
                $latest_version   = $plugin_updates->response[ $plugin_path ]->new_version;
            }

            $plugins_data[] = array(
                'name'             => $plugin_info['Name'],
                'version'          => $plugin_info['Version'],
                'active'           => $is_active,
                'update_available' => $update_available,
                'latest_version'   => $latest_version,
                'author'           => strip_tags( $plugin_info['Author'] ),
                'description'      => strip_tags( $plugin_info['Description'] ),
                'plugin_uri'       => $plugin_info['PluginURI'],
            );
        }

        return $plugins_data;
    }

    // -------------------------------------------------------------------------
    // Endpoint /backup (novo — lê dados do UpdraftPlus via wp_options)
    // -------------------------------------------------------------------------

    public function handle_backup_request( $request ) {
        try {
            if ( ! $this->is_updraftplus_active() ) {
                return rest_ensure_response( array( 'updraftplus_active' => false ) );
            }

            $version    = $this->get_updraftplus_version();
            $schedule   = $this->get_updraftplus_schedule();
            $history    = $this->get_updraftplus_history();
            $s3_storage = $this->get_s3_storage_info();

            $last_backup = ! empty( $history ) ? $history[0] : array(
                'status'           => 'never',
                'timestamp'        => null,
                'duration_seconds' => null,
                'files'            => array(),
                'error'            => null,
            );

            return rest_ensure_response( array(
                'updraftplus_active'   => true,
                'updraftplus_version'  => $version,
                'schedule'             => $schedule,
                'storage'             => $s3_storage,
                'last_backup'          => $last_backup,
                'history'              => $history,
            ) );

        } catch ( Exception $e ) {
            return new WP_Error(
                'backup_info_error',
                sprintf( __( 'Erro ao coletar informações de backup: %s', 'wp-system-rest-api' ), $e->getMessage() ),
                array( 'status' => 500 )
            );
        }
    }

    private function is_updraftplus_active() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        // Verificar se qualquer variante do UpdraftPlus está ativa
        $slugs = array(
            'updraftplus/updraftplus.php',
            'updraftplus-premium/updraftplus.php',
        );
        foreach ( $slugs as $slug ) {
            if ( is_plugin_active( $slug ) ) {
                return true;
            }
        }
        return false;
    }

    private function get_updraftplus_version() {
        if ( defined( 'UPDRAFTPLUS_VERSION' ) ) {
            return UPDRAFTPLUS_VERSION;
        }
        // Fallback: buscar via dados do plugin
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        foreach ( $plugins as $path => $info ) {
            if ( strpos( $path, 'updraftplus' ) !== false ) {
                return $info['Version'];
            }
        }
        return null;
    }

    private function get_s3_storage_info() {
        $raw = get_option( 'updraft_s3generic', null );

        if ( empty( $raw ) ) {
            return null;
        }

        $config = is_array( $raw ) ? $raw : maybe_unserialize( $raw );

        if ( ! is_array( $config ) ) {
            return null;
        }

        // Estrutura: { version, settings: { <hash>: { path, endpoint, ... } } }
        $settings = isset( $config['settings'] ) && is_array( $config['settings'] )
            ? $config['settings']
            : null;

        // Sem wrapper "settings" — estrutura plana (versões antigas)
        if ( $settings === null ) {
            $settings = array( $config );
        }

        // Pegar a primeira entrada ativa (instance_enabled = 1) ou a primeira disponível
        $entry = null;
        foreach ( $settings as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            if ( ! empty( $item['instance_enabled'] ) ) {
                $entry = $item;
                break;
            }
            if ( $entry === null ) {
                $entry = $item; // fallback: primeira entrada mesmo sem instance_enabled
            }
        }

        if ( $entry === null || ! isset( $entry['path'] ) ) {
            return null;
        }

        return array(
            'path' => trim( $entry['path'], '/' ),
        );
    }

    private function get_updraftplus_schedule() {
        $files_schedule = get_option( 'updraft_interval', 'manual' );
        $db_schedule    = get_option( 'updraft_interval_database', 'manual' );

        // Normalizar valores do UpdraftPlus para labels legíveis
        $label_map = array(
            'manual'    => 'manual',
            'daily'     => 'daily',
            'weekly'    => 'weekly',
            'fortnightly' => 'fortnightly',
            'monthly'   => 'monthly',
            'every4hours'  => 'every4hours',
            'every8hours'  => 'every8hours',
            'twicedaily'   => 'twicedaily',
        );

        return array(
            'files'    => isset( $label_map[ $files_schedule ] ) ? $label_map[ $files_schedule ] : $files_schedule,
            'database' => isset( $label_map[ $db_schedule ] ) ? $label_map[ $db_schedule ] : $db_schedule,
        );
    }

    private function get_updraftplus_history() {
        // O UpdraftPlus armazena o histórico completo nesta chave
        $raw_history = get_option( 'updraft_backup_history', array() );

        if ( ! is_array( $raw_history ) || empty( $raw_history ) ) {
            return array();
        }

        $history = array();

        // Cada entrada é indexada pelo timestamp Unix do backup
        foreach ( $raw_history as $timestamp => $backup_data ) {
            if ( ! is_array( $backup_data ) ) {
                continue;
            }

            $entry = $this->parse_backup_entry( (int) $timestamp, $backup_data );
            if ( $entry ) {
                $history[] = $entry;
            }
        }

        // Ordenar do mais recente para o mais antigo
        usort( $history, function( $a, $b ) {
            return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
        } );

        // Retornar apenas os últimos 10
        return array_slice( $history, 0, 10 );
    }

    private function parse_backup_entry( $timestamp, $backup_data ) {
        $files   = array();
        $has_err = false;
        $error   = null;

        // Tipos de componentes que o UpdraftPlus faz backup
        $component_types = array( 'db', 'plugins', 'themes', 'uploads', 'others' );

        // Nomes de arquivos já enviados ao remoto (gravados pelo UpdraftPlus após upload)
        $remote_sent = array();
        if ( ! empty( $backup_data['remote_sent'] ) && is_array( $backup_data['remote_sent'] ) ) {
            foreach ( $backup_data['remote_sent'] as $sent_files ) {
                if ( is_array( $sent_files ) ) {
                    $remote_sent = array_merge( $remote_sent, $sent_files );
                } elseif ( is_string( $sent_files ) ) {
                    $remote_sent[] = $sent_files;
                }
            }
        }

        foreach ( $component_types as $type ) {
            if ( empty( $backup_data[ $type ] ) ) {
                continue;
            }

            $component_files = is_array( $backup_data[ $type ] )
                ? $backup_data[ $type ]
                : array( $backup_data[ $type ] );

            foreach ( $component_files as $filename ) {
                if ( ! is_string( $filename ) ) {
                    continue;
                }

                $local_path  = trailingslashit( WP_CONTENT_DIR ) . 'updraft/' . $filename;
                $exists_local = file_exists( $local_path );
                $exists_remote = in_array( $filename, $remote_sent, true );

                $size = 0;
                if ( $exists_local ) {
                    $size = (int) filesize( $local_path );
                } elseif ( isset( $backup_data[ $type . '-size' ] ) ) {
                    // UpdraftPlus armazena o tamanho total do componente em "{type}-size"
                    // quando os arquivos já foram enviados ao remoto e deletados localmente
                    $type_size = $backup_data[ $type . '-size' ];
                    $component_files_count = count( $component_files );
                    $size = $component_files_count > 0 ? (int) ( $type_size / $component_files_count ) : 0;
                }

                // Determinar localização do arquivo
                if ( $exists_local && $exists_remote ) {
                    $storage = 'both';
                } elseif ( $exists_remote ) {
                    $storage = 's3';
                } elseif ( $exists_local ) {
                    $storage = 'local';
                } else {
                    // Arquivo não encontrado localmente mas registrado no histórico = remoto
                    $storage = 's3';
                }

                $files[] = array(
                    'type'       => $type === 'db' ? 'database' : $type,
                    'filename'   => $filename,
                    'size_bytes' => $size,
                    'storage'    => $storage,
                );
            }
        }

        // Verificar erros registrados pelo UpdraftPlus
        if ( ! empty( $backup_data['jobdata'] ) ) {
            $jobdata = maybe_unserialize( $backup_data['jobdata'] );
            if ( is_array( $jobdata ) && ! empty( $jobdata['last_error'] ) ) {
                $has_err = true;
                $error   = $jobdata['last_error'];
            }
        }

        // Determinar status
        if ( $has_err ) {
            $status = 'failed';
        } elseif ( ! empty( $backup_data['nonce'] ) && empty( $files ) ) {
            // Backup iniciado mas sem arquivos registrados = pode estar rodando
            $status = 'running';
        } elseif ( ! empty( $files ) ) {
            $status = 'success';
        } else {
            $status = 'failed';
        }

        // Calcular duração se disponível via jobdata
        $duration = null;
        if ( ! empty( $backup_data['jobdata'] ) ) {
            $jobdata = maybe_unserialize( $backup_data['jobdata'] );
            if ( is_array( $jobdata ) ) {
                $start = isset( $jobdata['job_time_ms'] ) ? (float) $jobdata['job_time_ms'] / 1000 : null;
                $end   = isset( $jobdata['time_last_success_no_warnings_or_errors'] )
                    ? (float) $jobdata['time_last_success_no_warnings_or_errors']
                    : null;
                if ( $start && $end && $end > $start ) {
                    $duration = (int) round( $end - $start );
                }
            }
        }

        return array(
            'status'           => $status,
            'timestamp'        => gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ),
            'duration_seconds' => $duration,
            'files'            => $files,
            'error'            => $error,
        );
    }
}

function wp_system_rest_api_init() {
    new WP_System_REST_API();
}
add_action( 'plugins_loaded', 'wp_system_rest_api_init' );
