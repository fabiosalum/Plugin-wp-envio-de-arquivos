<?php
/**
 * Plugin Name: eduCAT Documentos
 * Description: Envio em lote, gestão por categoria e ano e catálogo público de documentos.
 * Version: 1.2.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: F2Digital
 * License: GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

final class F2_Documentos_Institucionais {
	const POST_TYPE = 'f2di_documento';
	const CATEGORY = 'f2di_categoria';
	const YEAR = 'f2di_ano';
	const MENU = 'f2di-enviar';
	const PAGE_OPTION = 'f2di_catalog_page';
	const FILE_META = '_f2di_attachment';
	const SHORTCODE = 'educat_documentos';
	const DISABLED_META = '_f2di_disabled';
	const VERSION = '1.2.0';
	const FORMATS = 'pdf docx doc mp3 mp4 ppt pptx pps xls xlsx txt zip';

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'public_assets' ) );
		add_action( 'admin_post_f2di_upload', array( __CLASS__, 'upload' ) );
		add_action( 'admin_post_f2di_create_page', array( __CLASS__, 'create_page_action' ) );
		add_action( 'admin_post_f2di_visibility', array( __CLASS__, 'update_visibility' ) );
		add_action( 'admin_footer', array( __CLASS__, 'visibility_form' ) );
		add_filter( 'manage_edit-' . self::CATEGORY . '_columns', array( __CLASS__, 'category_columns' ) );
		add_filter( 'manage_' . self::CATEGORY . '_custom_column', array( __CLASS__, 'category_column' ), 10, 3 );
		add_action( self::CATEGORY . '_edit_form_fields', array( __CLASS__, 'category_edit_status' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'admin_filters' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_admin_filters' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'edit_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_year' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'deleted_post', array( __CLASS__, 'remove_deleted_attachment_link' ), 10, 2 );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'catalog' ) );
		add_shortcode( 'f2_documentos', array( __CLASS__, 'catalog' ) );
	}

	public static function register() {
		$capabilities = array(
			'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts',
			'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts',
		);
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'          => 'Documentos',
					'singular_name' => 'Documento',
					'edit_item'     => 'Editar documento',
					'search_items'  => 'Pesquisar documentos',
					'not_found'     => 'Nenhum documento encontrado.',
					'all_items'     => 'Documentos',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => self::MENU,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'rewrite'         => false,
				'map_meta_cap'    => true,
				'capabilities'    => array_fill_keys( $capabilities, 'manage_options' ) + array( 'create_posts' => 'do_not_allow' ),
			)
		);
		$term_caps = array_fill_keys( array( 'manage_terms', 'edit_terms', 'delete_terms', 'assign_terms' ), 'manage_options' );
		register_taxonomy(
			self::CATEGORY,
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'              => 'Categorias',
					'singular_name'     => 'Categoria',
					'search_items'      => 'Pesquisar categorias',
					'all_items'         => 'Todas as categorias',
					'edit_item'         => 'Editar categoria',
					'add_new_item'      => 'Adicionar categoria',
					'new_item_name'     => 'Nome da nova categoria',
					'parent_item'       => 'Categoria superior',
					'menu_name'         => 'Categorias',
				),
				'public'        => false,
				'show_ui'       => true,
				'show_in_menu'  => false,
				'hierarchical'  => true,
				'rewrite'       => false,
				'capabilities'  => $term_caps,
			)
		);
		register_taxonomy(
			self::YEAR,
			self::POST_TYPE,
			array( 'public' => false, 'show_ui' => false, 'rewrite' => false, 'capabilities' => $term_caps )
		);
	}

	private static function page() {
		$page_id = (int) get_option( self::PAGE_OPTION );
		$page = $page_id ? get_post( $page_id ) : null;
		return $page && 'page' === $page->post_type ? $page : null;
	}

	public static function create_page() {
		if ( self::page() ) {
			return;
		}
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => 'Documentos Institucionais',
				'post_name'    => 'documentos-institucionais',
				'post_content' => '[' . self::SHORTCODE . ']',
			),
			true
		);
		if ( ! is_wp_error( $page_id ) && $page_id ) {
			update_option( self::PAGE_OPTION, $page_id );
		}
	}

	public static function create_page_action() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_pages' ) ) {
			wp_die( 'Sem permissão para criar a página.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'f2di_create_page' );
		self::create_page();
		$page = self::page();
		$edit_link = $page ? get_edit_post_link( $page->ID, 'raw' ) : '';
		wp_safe_redirect( $edit_link ?: self::upload_url() );
		exit;
	}

	public static function menu() {
		add_menu_page( 'Documentos institucionais', 'Documentos', 'manage_options', self::MENU, array( __CLASS__, 'upload_screen' ), 'dashicons-media-document' );
		add_submenu_page( self::MENU, 'Enviar documentos', 'Enviar', 'manage_options', self::MENU, array( __CLASS__, 'upload_screen' ), 0 );
		add_submenu_page( self::MENU, 'Categorias', 'Categorias', 'manage_options', 'edit-tags.php?taxonomy=' . self::CATEGORY . '&post_type=' . self::POST_TYPE );
	}

	private static function upload_url() {
		return admin_url( 'admin.php?page=' . self::MENU );
	}

	public static function admin_assets( $hook ) {
		if ( 'toplevel_page_' . self::MENU === $hook || self::is_management_screen() ) {
			wp_enqueue_style( 'f2di-admin', plugins_url( 'assets/admin.css', __FILE__ ), array(), self::VERSION );
		}
		if ( 'toplevel_page_' . self::MENU === $hook ) {
			wp_enqueue_script( 'f2di-admin', plugins_url( 'assets/admin.js', __FILE__ ), array(), self::VERSION, true );
		}
	}

	public static function public_assets() {
		wp_enqueue_style( 'f2di-catalog', plugins_url( 'assets/catalog.css', __FILE__ ), array(), self::VERSION );
	}

	private static function is_management_screen() {
		$screen = get_current_screen();
		return $screen && ( self::POST_TYPE === $screen->post_type || self::CATEGORY === $screen->taxonomy );
	}

	private static function logo() {
		?>
		<span class="f2di-brand"><img src="<?php echo esc_url( plugins_url( 'assets/logo-educat.png', __FILE__ ) ); ?>" width="1024" height="241" alt="eduCAT"></span>
		<?php
	}

	private static function is_disabled( $type, $id ) {
		return '1' === get_metadata( $type, $id, self::DISABLED_META, true );
	}

	private static function status_control( $type, $id, $name ) {
		if ( 'post' === $type && 'trash' === get_post_status( $id ) ) {
			echo '<span class="f2di-status-badge f2di-status-off">Na lixeira</span>';
			return;
		}
		$disabled = self::is_disabled( $type, $id );
		$status = 'term' === $type ? ( $disabled ? 'Desativada' : 'Ativa' ) : ( $disabled ? 'Desativado' : 'Ativo' );
		$action = $disabled ? 'Ativar' : 'Desativar';
		$intent = $type . ':' . $id . ':' . ( $disabled ? 'on' : 'off' );
		?>
		<div class="f2di-status">
			<span class="f2di-status-badge <?php echo $disabled ? 'f2di-status-off' : 'f2di-status-on'; ?>"><?php echo esc_html( $status ); ?></span>
			<button type="submit" form="f2di-status-form" class="button button-small" name="f2di_intent"
				value="<?php echo esc_attr( $intent . ':' . wp_create_nonce( 'f2di_visibility_' . $intent ) ); ?>"
				aria-label="<?php echo esc_attr( $action . ' ' . $name ); ?>"><?php echo esc_html( $action ); ?></button>
		</div>
		<?php
	}

	public static function visibility_form() {
		if ( ! self::is_management_screen() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<form id="f2di-status-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="f2di_visibility">
			<?php wp_referer_field(); ?>
		</form>
		<?php
	}

	public static function update_visibility() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão para alterar o status.', '', array( 'response' => 403 ) );
		}
		$request = self::request_text( $_POST, 'f2di_intent' );
		if ( ! preg_match( '/^((post|term):([1-9][0-9]*):(on|off)):([a-zA-Z0-9]+)$/D', $request, $parts )
			|| ! wp_verify_nonce( $parts[5], 'f2di_visibility_' . $parts[1] )
		) {
			wp_die( 'A solicitação expirou ou é inválida. Volte à lista e tente novamente.', '', array( 'response' => 403 ) );
		}
		$type = $parts[2];
		$id = (int) $parts[3];
		$valid = 'post' === $type
			? self::POST_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $id )
			: term_exists( $id, self::CATEGORY ) && current_user_can( 'edit_term', $id );
		if ( ! $valid ) {
			wp_die( 'Não foi possível alterar este item.', '', array( 'response' => 403 ) );
		}
		$disable = 'off' === $parts[4];
		if ( $disable ) {
			update_metadata( $type, $id, self::DISABLED_META, '1' );
		} else {
			delete_metadata( $type, $id, self::DISABLED_META );
		}
		if ( $disable !== self::is_disabled( $type, $id ) ) {
			wp_die( 'Não foi possível salvar o status. Tente novamente.', '', array( 'response' => 500 ) );
		}
		$fallback = admin_url( 'post' === $type ? 'edit.php?post_type=' . self::POST_TYPE : 'edit-tags.php?taxonomy=' . self::CATEGORY . '&post_type=' . self::POST_TYPE );
		wp_safe_redirect( wp_get_referer() ?: $fallback, 303 );
		exit;
	}

	private static function request_text( $values, $key ) {
		return isset( $values[ $key ] ) && is_string( $values[ $key ] ) ? sanitize_text_field( wp_unslash( $values[ $key ] ) ) : '';
	}

	private static function terms( $taxonomy, $hide_empty = false ) {
		$args = array( 'taxonomy' => $taxonomy, 'hide_empty' => $hide_empty );
		if ( self::YEAR === $taxonomy ) {
			$args['order'] = 'DESC';
		}
		$terms = get_terms( $args );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	private static function active_categories() {
		$active = array();
		foreach ( self::terms( self::CATEGORY ) as $term ) {
			if ( ! self::is_disabled( 'term', $term->term_id ) ) {
				$active[ $term->term_id ] = $term;
			}
		}
		return $active;
	}

	private static function allowed_mimes() {
		$allowed = get_allowed_mime_types();
		$result = array();
		foreach ( explode( ' ', self::FORMATS ) as $ext ) {
			$type = wp_check_filetype( 'documento.' . $ext, $allowed );
			if ( $type['type'] ) {
				$result[ $ext ] = $type['type'];
			}
		}
		return $result;
	}

	private static function max_files() {
		$limit = (int) ini_get( 'max_file_uploads' );
		return $limit > 0 ? min( 20, $limit ) : 20;
	}

	private static function valid_year( $year ) {
		return preg_match( '/^[0-9]{4}$/D', $year ) && (int) $year >= 1000;
	}

	private static function category_options( $parent, $groups ) {
		foreach ( $groups[ $parent ] ?? array() as $term ) {
			echo '<li><label><input type="checkbox" name="f2di_categories[]" value="' . esc_attr( $term->term_id ) . '"> ' . esc_html( $term->name ) . '</label>';
			if ( ! empty( $groups[ $term->term_id ] ) ) {
				echo '<ul>';
				self::category_options( $term->term_id, $groups );
				echo '</ul>';
			}
			echo '</li>';
		}
	}

	public static function upload_screen() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão para acessar esta tela.', '', array( 'response' => 403 ) );
		}
		$token = self::request_text( $_GET, 'f2di_result' );
		$notice = false;
		if ( preg_match( '/^[a-zA-Z0-9]{16}$/D', $token ) ) {
			$key = 'f2di_result_' . get_current_user_id() . '_' . $token;
			$notice = get_transient( $key );
			delete_transient( $key );
		}
		$categories = self::active_categories();
		$category_groups = array();
		foreach ( $categories as $term ) {
			$parent = isset( $categories[ $term->parent ] ) ? $term->parent : 0;
			$category_groups[ $parent ][] = $term;
		}
		$mimes = self::allowed_mimes();
		$page = self::page();
		?>
		<div class="wrap f2di-admin">
			<header class="f2di-admin-hero">
				<div>
					<span class="f2di-admin-eyebrow">Central de documentos</span>
					<h1>Enviar documentos</h1>
					<p>Adicione arquivos ao catálogo e organize a consulta por categoria e ano.</p>
				</div>
				<?php self::logo(); ?>
			</header>
			<?php if ( is_array( $notice ) ) : ?>
				<?php if ( $notice['sent'] ) : ?>
					<div class="notice notice-success"><p><?php echo esc_html( sprintf( 1 === $notice['sent'] ? '%d documento enviado com sucesso.' : '%d documentos enviados com sucesso.', $notice['sent'] ) ); ?></p></div>
				<?php endif; ?>
				<?php if ( $notice['errors'] ) : ?>
					<div class="notice notice-error"><ul><?php foreach ( $notice['errors'] as $error ) : ?><li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul></div>
				<?php endif; ?>
			<?php endif; ?>
			<div class="f2di-admin-catalog">
				<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
				<?php if ( $page && 'trash' === $page->post_status ) : ?>
					<p>O catálogo está na lixeira. Restaure a página em <strong>Páginas</strong>.</p>
				<?php elseif ( $page && get_edit_post_link( $page->ID, 'raw' ) ) : ?>
					<p><a href="<?php echo esc_url( get_edit_post_link( $page->ID, 'raw' ) ); ?>">Editar a página do catálogo</a>
					<?php if ( 'publish' !== $page->post_status ) : ?> · Publique a página para liberar o catálogo.<?php endif; ?></p>
					<?php if ( 'publish' === $page->post_status ) : ?><a class="f2di-admin-view" href="<?php echo esc_url( get_permalink( $page ) ); ?>">Ver página pública</a><?php endif; ?>
				<?php elseif ( ! $page && current_user_can( 'edit_pages' ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="f2di_create_page">
						<?php wp_nonce_field( 'f2di_create_page' ); ?>
						<?php submit_button( 'Criar página do catálogo', 'secondary', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</div>
			<form class="f2di-upload" method="post" enctype="multipart/form-data" action="<?php echo esc_url( add_query_arg( 'action', 'f2di_upload', admin_url( 'admin-post.php' ) ) ); ?>">
				<?php wp_nonce_field( 'f2di_upload' ); ?>
				<div class="f2di-upload-section">
					<h2><span class="f2di-step" aria-hidden="true">1</span> Arquivos</h2>
					<label class="f2di-label" for="f2di-files">Selecionar arquivos</label>
					<input id="f2di-files" name="f2di_files[]" type="file" multiple required data-limit="<?php echo esc_attr( self::max_files() ); ?>"
						aria-describedby="f2di-upload-help" accept="<?php echo esc_attr( '.' . implode( ',.', array_keys( $mimes ) ) ); ?>">
					<p id="f2di-upload-help" class="description">
						<?php echo esc_html( $mimes ? implode( ', ', array_map( 'strtoupper', array_keys( $mimes ) ) ) : 'Nenhum formato disponível neste site.' ); ?>
						<?php echo esc_html( sprintf( ' · Até %d arquivos por envio · %s por arquivo, sujeito ao limite total do servidor.', self::max_files(), size_format( wp_max_upload_size() ) ) ); ?>
					</p>
					<input type="hidden" id="f2di-file-count" name="f2di_file_count" value="">
					<p id="f2di-file-status" role="status"></p>
				</div>
				<div class="f2di-upload-section">
					<h2><span class="f2di-step" aria-hidden="true">2</span> Organização do lote</h2>
					<fieldset class="f2di-fieldset">
						<legend class="f2di-label">Categorias</legend>
						<?php if ( $categories ) : ?>
							<label class="screen-reader-text" for="f2di-category-search">Pesquisar categorias</label>
							<input id="f2di-category-search" type="search" placeholder="Pesquisar categorias" hidden>
							<ul class="f2di-categories"><?php self::category_options( 0, $category_groups ); ?></ul>
							<p id="f2di-category-empty" class="description" hidden>Nenhuma categoria encontrada.</p>
						<?php else : ?>
							<p><a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . self::CATEGORY . '&post_type=' . self::POST_TYPE ) ); ?>">Cadastre ou ative uma categoria</a> para liberar o envio.</p>
						<?php endif; ?>
					</fieldset>
					<label class="f2di-label" for="f2di-upload-year">Ano dos documentos</label>
					<input id="f2di-upload-year" type="number" name="f2di_year" min="1000" max="9999" required value="<?php echo esc_attr( wp_date( 'Y' ) ); ?>">
					<p class="description">O ano e as categorias selecionadas serão aplicados a todos os arquivos deste envio.</p>
				</div>
				<div class="f2di-upload-footer">
					<p>Os arquivos ficam acessíveis pelo link direto a partir do envio.</p>
					<?php submit_button( 'Enviar documentos', 'primary', 'submit', true, $categories && $mimes && current_user_can( 'upload_files' ) ? array() : array( 'disabled' => true ) ); ?>
				</div>
			</form>
		</div>
		<?php
	}

	private static function upload_result( $sent, $errors ) {
		$token = wp_generate_password( 16, false );
		set_transient( 'f2di_result_' . get_current_user_id() . '_' . $token, compact( 'sent', 'errors' ), 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'f2di_result', $token, self::upload_url() ) );
		exit;
	}

	public static function upload() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'upload_files' ) ) {
			wp_die( 'Sem permissão para enviar documentos.', '', array( 'response' => 403 ) );
		}
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( 'Use o formulário de envio.', '', array( 'response' => 405 ) );
		}
		if ( empty( $_POST ) && empty( $_FILES ) ) {
			$limit = wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) );
			if ( $limit > 0 && (int) ( $_SERVER['CONTENT_LENGTH'] ?? 0 ) > $limit ) {
				self::upload_result( 0, array( 'O lote excede o tamanho total permitido pelo servidor. Divida o envio em lotes menores.' ) );
			}
		}
		check_admin_referer( 'f2di_upload' );

		$selected = isset( $_POST['f2di_categories'] ) && is_array( $_POST['f2di_categories'] ) ? wp_unslash( $_POST['f2di_categories'] ) : array();
		$category_ids = array();
		foreach ( $selected as $value ) {
			if ( ! is_string( $value ) || ! ctype_digit( $value ) || ! term_exists( (int) $value, self::CATEGORY ) || self::is_disabled( 'term', (int) $value ) ) {
				self::upload_result( 0, array( 'Selecione apenas categorias ativas.' ) );
			}
			$category_ids[] = (int) $value;
		}
		$year = self::request_text( $_POST, 'f2di_year' );
		$batch = $_FILES['f2di_files'] ?? array();
		$count = isset( $batch['name'] ) && is_array( $batch['name'] ) ? count( $batch['name'] ) : 0;
		if ( ! $category_ids || ! self::valid_year( $year ) || ! $count || $count > self::max_files() ) {
			self::upload_result( 0, array( 'Selecione arquivos dentro do limite exibido, pelo menos uma categoria e um ano válido.' ) );
		}

		// The browser supplies its selection size so a truncated PHP batch never silently succeeds.
		$expected = (int) self::request_text( $_POST, 'f2di_file_count' );
		if ( $expected > $count ) {
			self::upload_result( 0, array( 'O servidor recebeu apenas parte do lote. Reduza a quantidade de arquivos e tente novamente.' ) );
		}
		$year_id = self::year_term( $year );
		if ( ! $year_id ) {
			self::upload_result( 0, array( 'Não foi possível cadastrar o ano informado.' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$mimes = self::allowed_mimes();
		$sent = 0;
		$errors = array();
		foreach ( $batch['name'] as $index => $name ) {
			$file = array();
			foreach ( array( 'name', 'type', 'tmp_name', 'error', 'size' ) as $field ) {
				$value = $batch[ $field ][ $index ] ?? null;
				if ( ! is_scalar( $value ) ) {
					$errors[] = 'Um dos arquivos recebidos é inválido.';
					continue 2;
				}
				$file[ $field ] = $value;
			}
			$result = self::save_upload( $file, $mimes, $category_ids, $year_id );
			if ( is_wp_error( $result ) ) {
				$errors[] = sanitize_text_field( $name ) . ' — ' . $result->get_error_message();
			} else {
				++$sent;
			}
		}
		self::upload_result( $sent, $errors );
	}

	private static function year_term( $year ) {
		$term = get_term_by( 'slug', $year, self::YEAR );
		if ( $term ) {
			return (int) $term->term_id;
		}
		$result = wp_insert_term( $year, self::YEAR, array( 'slug' => $year ) );
		if ( is_wp_error( $result ) ) {
			return (int) $result->get_error_data( 'term_exists' );
		}
		return (int) $result['term_id'];
	}

	private static function save_upload( $file, $mimes, $categories, $year_id ) {
		if ( UPLOAD_ERR_OK === (int) $file['error'] ) {
			$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
			if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
				return new WP_Error( 'f2di_type', 'Formato não permitido ou arquivo incompatível com a extensão.' );
			}
		}
		$upload = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => $mimes ) );
		if ( isset( $upload['error'] ) ) {
			return new WP_Error( 'f2di_upload', $upload['error'] );
		}
		$title = sanitize_text_field( pathinfo( wp_basename( $file['name'] ), PATHINFO_FILENAME ) ) ?: 'Documento';
		$post_id = wp_insert_post(
			array( 'post_type' => self::POST_TYPE, 'post_title' => wp_slash( $title ), 'post_status' => 'draft' ),
			true
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_delete_file( $upload['file'] );
			return new WP_Error( 'f2di_post', 'Não foi possível criar o documento.' );
		}
		$attachment = wp_insert_attachment(
			array( 'post_title' => wp_slash( $title ), 'post_mime_type' => $upload['type'], 'guid' => $upload['url'] ),
			$upload['file'],
			$post_id,
			true
		);
		if ( is_wp_error( $attachment ) || ! $attachment ) {
			wp_delete_post( $post_id, true );
			wp_delete_file( $upload['file'] );
			return new WP_Error( 'f2di_attachment', 'Não foi possível registrar o arquivo na biblioteca de mídia.' );
		}
		$assigned_categories = wp_set_object_terms( $post_id, array_values( array_unique( $categories ) ), self::CATEGORY );
		$assigned_year = wp_set_object_terms( $post_id, array( $year_id ), self::YEAR );
		if ( ! is_wp_error( $assigned_categories ) && count( $assigned_categories ) === count( array_unique( $categories ) )
			&& ! is_wp_error( $assigned_year ) && count( $assigned_year ) === 1
			&& add_post_meta( $post_id, self::FILE_META, $attachment, true )
		) {
			$published = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ), true );
			if ( ! is_wp_error( $published ) && $published && 'publish' === get_post_status( $post_id ) ) {
				return $post_id;
			}
		}
		wp_delete_post( $post_id, true );
		wp_delete_attachment( $attachment, true );
		return new WP_Error( 'f2di_publish', 'Não foi possível publicar o documento.' );
	}

	public static function remove_deleted_attachment_link( $post_id, $post ) {
		if ( 'attachment' === $post->post_type && self::POST_TYPE === get_post_type( $post->post_parent ) ) {
			delete_post_meta( $post->post_parent, self::FILE_META, $post_id );
		}
	}

	public static function columns( $columns ) {
		unset( $columns['date'] );
		return $columns + array( 'f2di_category' => 'Categorias', 'f2di_year' => 'Ano', 'f2di_status' => 'Status no catálogo', 'f2di_file' => 'Arquivo', 'date' => 'Data' );
	}

	public static function category_columns( $columns ) {
		return $columns + array( 'f2di_status' => 'Status no catálogo' );
	}

	public static function category_column( $value, $column, $term_id ) {
		if ( 'f2di_status' !== $column ) {
			return $value;
		}
		$term = get_term( $term_id, self::CATEGORY );
		if ( ! $term || is_wp_error( $term ) ) {
			return $value;
		}
		ob_start();
		self::status_control( 'term', $term_id, $term->name );
		return ob_get_clean();
	}

	public static function category_edit_status( $term ) {
		?>
		<tr class="form-field">
			<th scope="row">Status no catálogo</th>
			<td>
				<?php self::status_control( 'term', $term->term_id, $term->name ); ?>
				<p class="description">Documentos vinculados apenas a categorias desativadas saem do catálogo. Subcategorias têm status próprio.</p>
			</td>
		</tr>
		<?php
	}

	private static function document_terms( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		return is_array( $terms ) ? wp_list_pluck( $terms, 'name' ) : array();
	}

	private static function attachment_id( $post_id ) {
		return (int) get_post_meta( $post_id, self::FILE_META, true );
	}

	public static function column( $column, $post_id ) {
		if ( 'f2di_status' === $column ) {
			self::status_control( 'post', $post_id, get_the_title( $post_id ) );
			return;
		}
		if ( 'f2di_file' === $column ) {
			$url = wp_get_attachment_url( self::attachment_id( $post_id ) );
			echo $url ? '<a href="' . esc_url( $url ) . '">Abrir arquivo</a>' : 'Arquivo indisponível';
			return;
		}
		$taxonomy = 'f2di_year' === $column ? self::YEAR : self::CATEGORY;
		echo esc_html( implode( ', ', self::document_terms( $post_id, $taxonomy ) ) ?: '—' );
	}

	public static function row_actions( $actions, $post ) {
		if ( self::POST_TYPE === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}

	private static function select_filter( $name, $taxonomy, $label, $selected, $public = false, $terms = null ) {
		$id = $public ? wp_unique_id( $name . '-' ) : $name;
		?>
		<label for="<?php echo esc_attr( $id ); ?>"<?php echo $public ? '' : ' class="screen-reader-text"'; ?>><?php echo esc_html( $label ); ?></label>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
			<option value=""><?php echo self::YEAR === $taxonomy ? 'Todos os anos' : 'Todas as categorias'; ?></option>
			<?php foreach ( $terms ?? self::terms( $taxonomy, $public ) as $term ) : ?>
				<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $selected, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public static function admin_filters( $post_type, $which ) {
		if ( self::POST_TYPE === $post_type && 'top' === $which ) {
			self::select_filter( 'f2di_cat', self::CATEGORY, 'Categoria', self::request_text( $_GET, 'f2di_cat' ) );
			self::select_filter( 'f2di_year', self::YEAR, 'Ano', self::request_text( $_GET, 'f2di_year' ) );
		}
	}

	private static function taxonomy_filters( $category, $year ) {
		$filters = array();
		foreach ( array( self::CATEGORY => $category, self::YEAR => $year ) as $taxonomy => $term_id ) {
			if ( $term_id > 0 ) {
				$filters[] = array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => array( $term_id ) );
			}
		}
		return $filters;
	}

	private static function catalog_categories( $category, $active_categories ) {
		if ( ! $category ) {
			return $active_categories;
		}
		if ( ! isset( $active_categories[ $category ] ) ) {
			return array();
		}
		$children = get_term_children( $category, self::CATEGORY );
		$ids = array_merge( array( $category ), is_array( $children ) ? $children : array() );
		return array_intersect_key( $active_categories, array_flip( $ids ) );
	}

	private static function category_clause( $ids ) {
		return array( 'taxonomy' => self::CATEGORY, 'field' => 'term_id', 'terms' => $ids, 'include_children' => false );
	}

	public static function apply_admin_filters( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		$filters = self::taxonomy_filters( absint( self::request_text( $_GET, 'f2di_cat' ) ), absint( self::request_text( $_GET, 'f2di_year' ) ) );
		if ( $filters ) {
			$query->set( 'tax_query', array_merge( (array) $query->get( 'tax_query', array() ), $filters ) );
		}
	}

	public static function edit_boxes( $post ) {
		add_meta_box( 'f2di-details', 'Dados do documento', array( __CLASS__, 'edit_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public static function edit_box( $post ) {
		wp_nonce_field( 'f2di_save_year', 'f2di_edit_nonce' );
		$years = self::document_terms( $post->ID, self::YEAR );
		$url = wp_get_attachment_url( self::attachment_id( $post->ID ) );
		?>
		<p><label for="f2di-edit-year">Ano do documento</label><br>
		<input id="f2di-edit-year" type="number" name="f2di_document_year" min="1000" max="9999" required value="<?php echo esc_attr( $years[0] ?? wp_date( 'Y' ) ); ?>"></p>
		<p><?php if ( $url ) : ?><a href="<?php echo esc_url( $url ); ?>">Abrir arquivo</a><?php else : ?>Arquivo indisponível.<?php endif; ?></p>
		<p><strong>Status no catálogo</strong></p>
		<?php self::status_control( 'post', $post->ID, get_the_title( $post ) ); ?>
		<p class="description">Desativar retira o documento do catálogo. O link direto do arquivo de mídia continua acessível.</p>
		<?php
	}

	public static function save_year( $post_id ) {
		$nonce = self::request_text( $_POST, 'f2di_edit_nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'f2di_save_year' ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$year = self::request_text( $_POST, 'f2di_document_year' );
		$term = self::valid_year( $year ) ? self::year_term( $year ) : 0;
		if ( $term ) {
			wp_set_object_terms( $post_id, array( $term ), self::YEAR );
		}
	}

	public static function catalog( $attributes ) {
		$attributes = shortcode_atts( array( 'por_pagina' => 12 ), (array) $attributes, self::SHORTCODE );
		$search = wp_html_excerpt( self::request_text( $_GET, 'f2di_busca' ), 200 );
		$category = absint( self::request_text( $_GET, 'f2di_cat' ) );
		$year = absint( self::request_text( $_GET, 'f2di_year' ) );
		$current = max( 1, min( 100000, absint( self::request_text( $_GET, 'f2di_pagina' ) ) ) );
		$group = self::request_text( $_GET, 'f2di_grupo' );
		$open_group = ctype_digit( $group ) ? (int) $group : null;
		$base = get_permalink( get_queried_object_id() ) ?: home_url( '/' );
		$form_action = strtok( $base, '?' );
		parse_str( (string) wp_parse_url( $base, PHP_URL_QUERY ), $base_params );
		$active_categories = self::active_categories();
		$categories = self::catalog_categories( $category, $active_categories );
		$uncategorized = array( 'taxonomy' => self::CATEGORY, 'operator' => 'NOT EXISTS' );
		$categorized = self::category_clause( array_keys( $categories ) );
		$args = array(
			'post_type'           => self::POST_TYPE,
			'post_status'         => 'publish',
			'has_password'        => false,
			'posts_per_page'      => max( 1, min( 60, (int) $attributes['por_pagina'] ) ),
			'orderby'             => array( 'date' => 'DESC', 'ID' => 'DESC' ),
			's'                   => $search,
			'search_columns'      => array( 'post_title' ),
			'tax_query'           => self::taxonomy_filters( 0, $year ),
			'meta_query'          => array(
				array( 'key' => self::FILE_META, 'compare' => 'EXISTS' ),
				array( 'key' => self::DISABLED_META, 'compare' => 'NOT EXISTS' ),
			),
		);
		$count_args = array( 'posts_per_page' => 1, 'fields' => 'ids' ) + $args;
		$count_args['tax_query'][] = $category ? $categorized : array( 'relation' => 'OR', $categorized, $uncategorized );
		$results = new WP_Query( $count_args );
		$filters = array_filter( array( 'f2di_busca' => $search, 'f2di_cat' => $category, 'f2di_year' => $year ), static function ( $value ) {
			return '' !== $value && 0 !== $value;
		} );
		$search_id = wp_unique_id( 'f2di-search-' );
		static $instance = 0;
		$group_prefix = 'f2di-grupo-' . ++$instance . '-';
		ob_start();
		?>
		<section class="f2di-catalog" aria-label="Catálogo de documentos">
			<header class="f2di-hero">
				<div class="f2di-hero-copy">
					<span class="f2di-kicker">CENTRAL DE ARQUIVOS</span>
					<h2>Biblioteca de documentos</h2>
					<p>Consulte e baixe os arquivos da eduCAT por título, categoria ou ano.</p>
				</div>
				<div class="f2di-hero-mark" aria-hidden="true"><i></i><i></i><i></i></div>
			</header>
			<form class="f2di-filters" method="get" action="<?php echo esc_url( $form_action ); ?>" role="search" aria-label="Pesquisar documentos">
				<?php foreach ( $base_params as $key => $value ) : ?>
					<?php if ( is_scalar( $value ) ) : ?><input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"><?php endif; ?>
				<?php endforeach; ?>
				<div class="f2di-search-field">
					<label for="<?php echo esc_attr( $search_id ); ?>">Buscar por título</label>
					<input id="<?php echo esc_attr( $search_id ); ?>" name="f2di_busca" type="search" maxlength="200" placeholder="Ex.: edital ou manual" value="<?php echo esc_attr( $search ); ?>">
				</div>
				<div><?php self::select_filter( 'f2di_cat', self::CATEGORY, 'Categoria', $category, true, $active_categories ); ?></div>
				<div><?php self::select_filter( 'f2di_year', self::YEAR, 'Ano', $year, true ); ?></div>
				<button type="submit">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>
					Pesquisar
				</button>
			</form>
			<div class="f2di-results">
				<div>
					<h3>Documentos disponíveis</h3>
					<p><?php echo esc_html( sprintf( 1 === (int) $results->found_posts ? '%s documento' : '%s documentos', number_format_i18n( $results->found_posts ) ) ); ?></p>
				</div>
				<?php if ( $filters ) : ?><a href="<?php echo esc_url( $base ); ?>">Limpar filtros</a><?php endif; ?>
			</div>
			<?php if ( ! $results->posts ) : ?>
				<div class="f2di-empty">
					<strong><?php echo $filters ? 'Nenhum resultado encontrado' : 'Nenhum arquivo disponível'; ?></strong>
					<p><?php echo $filters ? 'Nenhum documento corresponde aos filtros selecionados.' : 'Ainda não há documentos publicados.'; ?></p>
				</div>
			<?php else : ?>
				<div class="f2di-groups">
					<?php
					foreach ( $categories as $id => $term ) {
						if ( $term->count > 0 ) {
							self::catalog_group( $id, $term->name, self::category_clause( array( $id ) ), $args, $group_prefix, $base, $filters, $open_group, $current );
						}
					}
					if ( ! $category ) {
						self::catalog_group( 0, 'Sem categoria', $uncategorized, $args, $group_prefix, $base, $filters, $open_group, $current );
					}
					?>
				</div>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function catalog_group( $id, $name, $clause, $args, $prefix, $base, $filters, $open_group, $current ) {
		$args['tax_query'][] = $clause;
		$page = $open_group === $id ? $current : 1;
		$args['paged'] = $page;
		$documents = new WP_Query( $args );
		if ( ! $documents->posts && $page > 1 ) {
			$page = 1;
			$args['paged'] = $page;
			$documents = new WP_Query( $args );
		}
		if ( ! $documents->posts ) {
			return;
		}
		$anchor = $prefix . $id;
		$open = null === $open_group ? isset( $filters['f2di_busca'] ) || isset( $filters['f2di_cat'] ) : $open_group === $id;
		$count = (int) $documents->found_posts;
		$page_args = $filters + array( 'f2di_grupo' => $id );
		?>
		<details class="f2di-group" id="<?php echo esc_attr( $anchor ); ?>"<?php echo $open ? ' open' : ''; ?>>
			<summary class="f2di-group-summary">
				<span class="f2di-folder" aria-hidden="true">
					<svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"><path d="M3 6a1 1 0 0 1 1-1h5l2 2h9a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/></svg>
				</span>
				<span class="f2di-group-name"><?php echo esc_html( $name ); ?></span>
				<span class="f2di-group-count"><?php echo esc_html( sprintf( 1 === $count ? '%s documento' : '%s documentos', number_format_i18n( $count ) ) ); ?></span>
				<svg class="f2di-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
			</summary>
			<ul class="f2di-list" role="list">
				<?php foreach ( $documents->posts as $document ) : ?>
					<?php self::catalog_row( $document ); ?>
				<?php endforeach; ?>
			</ul>
			<?php if ( $documents->max_num_pages > 1 ) : ?>
				<nav class="f2di-pages" aria-label="<?php echo esc_attr( 'Paginação de ' . $name ); ?>">
					<?php if ( $page > 1 ) : ?>
						<a href="<?php echo esc_url( add_query_arg( $page_args + array( 'f2di_pagina' => $page - 1 ), $base ) . '#' . $anchor ); ?>">Anterior</a>
					<?php endif; ?>
					<span aria-current="page"><?php echo esc_html( sprintf( 'Página %d de %d', $page, $documents->max_num_pages ) ); ?></span>
					<?php if ( $page < $documents->max_num_pages ) : ?>
						<a href="<?php echo esc_url( add_query_arg( $page_args + array( 'f2di_pagina' => $page + 1 ), $base ) . '#' . $anchor ); ?>">Próxima</a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		</details>
		<?php
	}

	private static function catalog_row( $document ) {
		$attachment = self::attachment_id( $document->ID );
		$url = $attachment ? wp_get_attachment_url( $attachment ) : false;
		$years = self::document_terms( $document->ID, self::YEAR );
		$format = $url ? strtoupper( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) ) : '';
		$title = get_the_title( $document ) ?: 'Documento';
		?>
		<li class="f2di-row">
			<span class="f2di-file-icon" aria-hidden="true">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"><path d="M6 3h8l4 4v14H6zM14 3v5h4M9 12h6m-6 4h6"/></svg>
			</span>
			<div class="f2di-row-copy">
				<span class="f2di-title"><?php echo esc_html( $title ); ?></span>
				<div class="f2di-row-meta">
					<?php if ( $format ) : ?><span class="f2di-format"><?php echo esc_html( $format ); ?></span><?php endif; ?>
					<?php if ( $years ) : ?><span class="f2di-year"><?php echo esc_html( 'Ano ' . $years[0] ); ?></span><?php endif; ?>
				</div>
			</div>
			<?php if ( $url ) : ?>
				<a class="f2di-download" href="<?php echo esc_url( $url ); ?>" download aria-label="<?php echo esc_attr( 'Baixar ' . $title ); ?>">
					Baixar arquivo
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M5 16v4h14v-4"/></svg>
				</a>
			<?php else : ?>
				<span class="f2di-year">Arquivo indisponível.</span>
			<?php endif; ?>
		</li>
		<?php
	}
}

F2_Documentos_Institucionais::boot();
register_activation_hook( __FILE__, array( 'F2_Documentos_Institucionais', 'create_page' ) );
