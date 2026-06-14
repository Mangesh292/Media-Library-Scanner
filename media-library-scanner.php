<?php
/**
 * Plugin Name: Media Library Scanner
 * Plugin URI: https://github.com/Mangesh292/Media-Library-Scanner
 * Description: Scan WordPress media library and identify used and unused images.
 * Version: 1.0.0
 * Author: Mangesh Jaiswal
 * Text Domain: media-library-scanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MLS_VERSION', '1.0.0' );
define( 'MLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MLS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action( 'admin_menu', 'mls_register_admin_menu' );

function mls_register_admin_menu() {
	add_media_page(
		__( 'Media Library Scanner', 'media-library-scanner' ),
		__( 'Scanner', 'media-library-scanner' ),
		'manage_options',
		'media-library-scanner',
		'mls_render_admin_page'
	);
}

function mls_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'media-library-scanner' ) );
	}

	$results = null;
	if ( isset( $_POST['mls_scan'] ) && check_admin_referer( 'mls_scan_action', 'mls_nonce' ) ) {
		$results = mls_run_scan();
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Media Library Scanner', 'media-library-scanner' ); ?></h1>
		<form method="post">
			<?php wp_nonce_field( 'mls_scan_action', 'mls_nonce' ); ?>
			<p>
				<input type="submit" name="mls_scan" class="button button-primary"
					value="<?php esc_attr_e( 'Scan Media Library', 'media-library-scanner' ); ?>">
			</p>
		</form>
		<?php if ( $results !== null ) : ?>
			<h2><?php esc_html_e( 'Scan Results', 'media-library-scanner' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: used count, 2: unused count */
					esc_html__( 'Used: %1$d &nbsp;|&nbsp; Unused: %2$d', 'media-library-scanner' ),
					count( $results['used'] ),
					count( $results['unused'] )
				);
				?>
			</p>
			<?php if ( ! empty( $results['unused'] ) ) : ?>
				<h3><?php esc_html_e( 'Unused Media', 'media-library-scanner' ); ?></h3>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'File', 'media-library-scanner' ); ?></th>
							<th><?php esc_html_e( 'Type', 'media-library-scanner' ); ?></th>
							<th><?php esc_html_e( 'Uploaded', 'media-library-scanner' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $results['unused'] as $item ) : ?>
							<tr>
								<td><?php echo esc_html( basename( $item['url'] ) ); ?></td>
								<td><?php echo esc_html( $item['mime'] ); ?></td>
								<td><?php echo esc_html( $item['date'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

function mls_run_scan() {
	$all_media = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );

	$used_ids   = mls_get_used_attachment_ids();
	$used       = array();
	$unused     = array();

	foreach ( $all_media as $id ) {
		$entry = array(
			'id'   => $id,
			'url'  => wp_get_attachment_url( $id ),
			'mime' => get_post_mime_type( $id ),
			'date' => get_the_date( 'Y-m-d', $id ),
		);

		if ( in_array( $id, $used_ids, true ) ) {
			$used[] = $entry;
		} else {
			$unused[] = $entry;
		}
	}

	return compact( 'used', 'unused' );
}

function mls_get_used_attachment_ids() {
	global $wpdb;

	$used_ids = array();

	// IDs set as featured image.
	$thumbnail_ids = $wpdb->get_col(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'"
	);
	$used_ids = array_merge( $used_ids, array_map( 'intval', $thumbnail_ids ) );

	// IDs referenced inside post content.
	$content_ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts}
		 WHERE post_status = 'publish'
		   AND post_type NOT IN ('attachment', 'revision')"
	);

	foreach ( $content_ids as $post_id ) {
		$post = get_post( $post_id );
		if ( preg_match_all( '/wp-image-(\d+)/', $post->post_content, $matches ) ) {
			$used_ids = array_merge( $used_ids, array_map( 'intval', $matches[1] ) );
		}
	}

	return array_unique( $used_ids );
}
