<?php
/**
 * Plugin Name: WP System REST API
 * Plugin URI: https://github.com/brunoalbim/wp-system-rest-api
 * Description: Expõe informações do sistema WordPress via REST API protegida por autenticação
 * Version: 1.0.0
 * Author: Bruno Albim
 * Author URI: https://github.com/brunoalbim
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-system-rest-api
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

// Previne acesso direto ao arquivo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe principal do plugin WP System REST API
 */
class WP_System_REST_API {

    /**
     * Namespace da API REST
     */
    const API_NAMESPACE = 'wp-system/v1';

    /**
     * Rota do endpoint
     */
    const API_ROUTE = '/info';

    /**
     * Construtor - registra os hooks necessários
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Registra as rotas da REST API
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            self::API_ROUTE,
            array(
                'methods'             => WP_REST_Server::READABLE, // GET
                'callback'            => array( $this, 'handle_system_info_request' ),
                'permission_callback' => array( $this, 'check_permission' ),
            )
        );
    }

    /**
     * Verifica se o usuário tem permissão para acessar o endpoint
     *
     * Este método funciona com Application Passwords (Senhas de Aplicação)
     * e qualquer outro método de autenticação suportado pelo WordPress
     *
     * @return bool|WP_Error True se autenticado, WP_Error caso contrário
     */
    public function check_permission() {
        // Verifica se o usuário está autenticado
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'Você precisa estar autenticado para acessar este endpoint.', 'wp-system-rest-api' ),
                array( 'status' => 401 )
            );
        }

        // Verifica se o usuário tem capacidade básica de leitura
        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'Você não tem permissão para acessar este recurso.', 'wp-system-rest-api' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Handler principal do endpoint - retorna todas as informações do sistema
     *
     * @param WP_REST_Request $request Objeto da requisição REST
     * @return WP_REST_Response|WP_Error Resposta com dados do sistema ou erro
     */
    public function handle_system_info_request( $request ) {
        try {
            // Coleta todas as informações do sistema
            $system_data = array(
                'wordpress_version' => $this->get_wordpress_version(),
                'php_version'       => $this->get_php_version(),
                'theme'             => $this->get_theme_info(),
                'plugins'           => $this->get_plugins_info(),
            );

            // Adiciona timestamp da requisição
            $system_data['timestamp'] = current_time( 'mysql' );
            $system_data['timestamp_gmt'] = current_time( 'mysql', true );

            return rest_ensure_response( $system_data );

        } catch ( Exception $e ) {
            return new WP_Error(
                'system_info_error',
                sprintf(
                    __( 'Erro ao coletar informações do sistema: %s', 'wp-system-rest-api' ),
                    $e->getMessage()
                ),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Obtém a versão do WordPress
     *
     * @return string Versão do WordPress
     */
    private function get_wordpress_version() {
        return get_bloginfo( 'version' );
    }

    /**
     * Obtém a versão do PHP
     *
     * @return string Versão do PHP
     */
    private function get_php_version() {
        return phpversion();
    }

    /**
     * Obtém informações do tema ativo
     *
     * @return array Informações do tema (nome, versão, atualização disponível)
     */
    private function get_theme_info() {
        $theme = wp_get_theme();
        
        // Obtém atualizações disponíveis para temas
        $theme_updates = get_site_transient( 'update_themes' );
        
        // Verifica se há atualização disponível para o tema ativo
        $stylesheet = get_stylesheet();
        $update_available = false;
        $latest_version = $theme->get( 'Version' );

        if ( isset( $theme_updates->response[ $stylesheet ] ) ) {
            $update_available = true;
            $latest_version = $theme_updates->response[ $stylesheet ]['new_version'];
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

    /**
     * Obtém informações de todos os plugins instalados (ativos e inativos)
     *
     * @return array Lista de plugins com suas informações
     */
    private function get_plugins_info() {
        // Garante que a função get_plugins() está disponível
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Obtém todos os plugins instalados
        $all_plugins = get_plugins();
        
        // Obtém atualizações disponíveis
        $plugin_updates = get_site_transient( 'update_plugins' );
        
        $plugins_data = array();

        foreach ( $all_plugins as $plugin_path => $plugin_info ) {
            // Verifica se o plugin está ativo
            $is_active = is_plugin_active( $plugin_path );
            
            // Verifica se há atualização disponível
            $update_available = false;
            $latest_version = $plugin_info['Version'];

            if ( isset( $plugin_updates->response[ $plugin_path ] ) ) {
                $update_available = true;
                $latest_version = $plugin_updates->response[ $plugin_path ]->new_version;
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
}

// Inicializa o plugin
function wp_system_rest_api_init() {
    new WP_System_REST_API();
}
add_action( 'plugins_loaded', 'wp_system_rest_api_init' );
