<?php
defined( 'ABSPATH' ) || exit;

final class F2DI_Updater {
	const REPOSITORY = 'fabiosalum/Plugin-wp-envio-de-arquivos';
	const SLUG = 'f2-documentos-institucionais';
	const CACHE_KEY = 'f2di_github_release_v1';

	private $plugin_file;

	public function __construct( $plugin_file ) {
		$this->plugin_file = plugin_basename( $plugin_file );
		add_filter( 'update_plugins_github.com', array( $this, 'update_offer' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'verify_download' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_post_f2di_check_updates', array( $this, 'check_now' ) );
		add_filter( 'plugin_action_links_' . $this->plugin_file, array( $this, 'action_links' ) );
	}

	private function repository_url() {
		return 'https://github.com/' . self::REPOSITORY;
	}

	private function settings_url() {
		return admin_url( 'admin.php?page=f2di-updates' );
	}

	private function installed_version() {
		$headers = get_file_data( WP_PLUGIN_DIR . '/' . $this->plugin_file, array( 'version' => 'Version' ) );
		return $headers['version'];
	}

	private function release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return isset( $cached['error'] ) ? new WP_Error( 'f2di_release', $cached['error'] ) : $cached;
		}

		$response = wp_safe_remote_get(
			$this->repository_url() . '/releases/latest/download/update.json',
			array(
				'timeout'             => 12,
				'redirection'         => 5,
				'limit_response_size' => 65536,
				'headers'             => array( 'Accept' => 'application/json' ),
				'user-agent'          => 'eduCAT-Documentos/' . $this->installed_version(),
			)
		);
		$release = $this->parse_response( $response );
		$cached = is_wp_error( $release ) ? array( 'error' => $release->get_error_message() ) : $release;
		$cached['checked_at'] = time();
		set_site_transient( self::CACHE_KEY, $cached, is_wp_error( $release ) ? 5 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS );
		return is_wp_error( $release ) ? $release : $cached;
	}

	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'f2di_release', 'Não foi possível consultar o GitHub. Tente novamente mais tarde.' );
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'f2di_release', 'O GitHub não disponibilizou os dados da versão. Verifique se a Release contém o arquivo update.json.' );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'f2di_release', 'Os dados da versão recebidos do GitHub são inválidos.' );
		}
		foreach ( array( 'version', 'requires', 'requires_php' ) as $field ) {
			if ( ! isset( $data[ $field ] ) || ! is_string( $data[ $field ] ) || ! preg_match( '/^[0-9]+(?:\.[0-9]+){1,3}$/D', $data[ $field ] ) ) {
				return new WP_Error( 'f2di_release', 'A versão ou os requisitos do pacote são inválidos.' );
			}
		}
		$package = $this->repository_url() . '/releases/download/v' . $data['version'] . '/' . self::SLUG . '.zip';
		if ( ! isset( $data['slug'], $data['package'], $data['sha256'] )
			|| self::SLUG !== $data['slug'] || $package !== $data['package']
			|| ! is_string( $data['sha256'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $data['sha256'] )
		) {
			return new WP_Error( 'f2di_release', 'O pacote recebido não corresponde ao repositório do plugin.' );
		}
		return array(
			'version'      => $data['version'],
			'requires'     => $data['requires'],
			'requires_php' => $data['requires_php'],
			'package'      => $package,
			'sha256'       => $data['sha256'],
		);
	}

	public function update_offer( $update, $plugin_data, $plugin_file ) {
		if ( $this->plugin_file !== $plugin_file ) {
			return $update;
		}
		$release = $this->release();
		if ( is_wp_error( $release ) ) {
			return $update;
		}
		return array(
			'slug'         => self::SLUG,
			'version'      => $release['version'],
			'url'          => $this->repository_url(),
			'package'      => $release['package'],
			'requires'     => $release['requires'],
			'requires_php' => $release['requires_php'],
		);
	}

	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}
		$release = $this->release();
		if ( is_wp_error( $release ) ) {
			return $release;
		}
		return (object) array(
			'name'          => 'eduCAT Documentos',
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => 'F2Digital',
			'homepage'      => $this->repository_url(),
			'requires'      => $release['requires'],
			'requires_php'  => $release['requires_php'],
			'download_link' => $release['package'],
			'sections'      => array(
				'description' => '<p>Envio e gestão de documentos com catálogo público por categoria e ano.</p>',
				'changelog'   => '<p><a href="' . esc_url( $this->repository_url() . '/releases' ) . '">Consultar o histórico de versões no GitHub</a></p>',
			),
		);
	}

	public function verify_download( $reply, $package, $upgrader, $hook_extra ) {
		if ( ! isset( $hook_extra['plugin'] ) || $this->plugin_file !== $hook_extra['plugin'] ) {
			return $reply;
		}
		$release = $this->release();
		if ( is_wp_error( $release ) ) {
			return $release;
		}
		// A cached offer can refer to a previous release. Refresh once before validating it.
		if ( $package !== $release['package'] ) {
			delete_site_transient( self::CACHE_KEY );
			$release = $this->release();
		}
		if ( is_wp_error( $release ) ) {
			return $release;
		}
		if ( $package !== $release['package'] ) {
			return new WP_Error( 'f2di_package', 'A versão publicada mudou. Verifique as atualizações e tente novamente.' );
		}
		if ( ! version_compare( get_bloginfo( 'version' ), $release['requires'], '>=' )
			|| ! version_compare( PHP_VERSION, $release['requires_php'], '>=' )
		) {
			return new WP_Error( 'f2di_requirements', 'Esta versão exige uma versão mais recente do WordPress ou do PHP.' );
		}
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$file = is_string( $reply ) && is_file( $reply ) ? $reply : download_url( $package );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( ! hash_equals( $release['sha256'], (string) hash_file( 'sha256', $file ) ) ) {
			wp_delete_file( $file );
			return new WP_Error( 'f2di_checksum', 'O arquivo baixado não corresponde ao pacote publicado. Tente novamente.' );
		}
		return $file;
	}

	public function clear_cache( $upgrader, $options ) {
		if ( 'plugin' === ( $options['type'] ?? '' ) && in_array( $this->plugin_file, $options['plugins'] ?? array(), true ) ) {
			delete_site_transient( self::CACHE_KEY );
		}
	}

	public function menu() {
		add_submenu_page( F2_Documentos_Institucionais::MENU, 'Atualizações do plugin', 'Atualizações', 'update_plugins', 'f2di-updates', array( $this, 'screen' ) );
	}

	public function action_links( $links ) {
		if ( current_user_can( 'update_plugins' ) ) {
			$links[] = '<a href="' . esc_url( $this->settings_url() ) . '">Verificar atualizações</a>';
		}
		return $links;
	}

	public function check_now() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( 'Sem permissão para verificar atualizações.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'f2di_check_updates' );
		delete_site_transient( self::CACHE_KEY );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		wp_safe_redirect( $this->settings_url(), 303 );
		exit;
	}

	public function screen() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( 'Sem permissão para visualizar atualizações.', '', array( 'response' => 403 ) );
		}
		$release = $this->release();
		$installed = $this->installed_version();
		?>
		<div class="wrap">
			<h1>Atualizações do eduCAT Documentos</h1>
			<p><?php echo esc_html( 'Versão instalada ' . $installed ); ?></p>
			<p>As versões do plugin são publicadas no repositório público do GitHub.</p>
			<?php if ( is_wp_error( $release ) ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( $release->get_error_message() ); ?></p></div>
			<?php elseif ( version_compare( $release['version'], $installed, '>' ) ) : ?>
				<div class="notice notice-info inline"><p><?php echo esc_html( 'Nova versão disponível — ' . $release['version'] ); ?></p></div>
				<p><a class="button button-primary" href="<?php echo esc_url( self_admin_url( 'plugins.php' ) ); ?>">Abrir plugins para atualizar</a></p>
			<?php else : ?>
				<div class="notice notice-success inline"><p>O plugin está atualizado.</p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="f2di_check_updates">
				<?php wp_nonce_field( 'f2di_check_updates' ); ?>
				<?php submit_button( 'Verificar agora', 'secondary' ); ?>
			</form>
			<p><a href="<?php echo esc_url( $this->repository_url() . '/releases' ); ?>">Ver versões publicadas no GitHub</a></p>
		</div>
		<?php
	}
}
