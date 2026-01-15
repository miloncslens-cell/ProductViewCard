<?php
/**
 * Plugin Name: Product View Card
 * Plugin URI:  https://abirmilon.website/
 * Description: Responsive WooCommerce product view cards with Quick Add (AJAX), admin Shortcode Manager, category targeting, 3/4/5 columns, transparent background and automatic theme fit.
 * Version:     1.1.1
 * Author:      MilonsLens
 * Author URI:  https://abirmilon.website/
 * Text Domain: product-view-card
 *
 * Single-file plugin. Drop into wp-content/plugins/product-view-card/product-view-card.php
 *
 * Shortcode usage:
 *  - Saved view: [product_view_card id="slug"]
 *  - Ad-hoc:    [product_view_card per_page="8" columns="4" category="oils" full_width="1"]
 */

/* Exit if accessed directly */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------
   Constants and defaults
   ----------------------- */
define( 'PVC_OPTION_KEY', 'pvc_options' );
define( 'PVC_SHORTCODES_KEY', 'pvc_saved_views' );
define( 'PVC_NONCE', 'pvc_quick_add' );

/* Activation: set defaults */
register_activation_hook( __FILE__, function() {
	$defaults = array(
		'quick_add_label' => 'Quick Add',
		'background'      => 'transparent', // 'transparent' or 'muted'
		// note: default_columns removed from settings per request; columns chosen per view only
		'logo_image_id'   => 0, // attachment ID for the top-left logo
	);
	$opts = get_option( PVC_OPTION_KEY, array() );
	$opts = wp_parse_args( $opts, $defaults );
	update_option( PVC_OPTION_KEY, $opts );

	if ( ! get_option( PVC_SHORTCODES_KEY ) ) {
		update_option( PVC_SHORTCODES_KEY, array() );
	}
} );

/* -----------------------
   Frontend assets: inline CSS & JS
   (Design unchanged from previous version)
   ----------------------- */
add_action( 'wp_enqueue_scripts', function() {
	// Register a handle to allow child themes to dequeue if needed
	wp_register_style( 'pvc-frontend', false );
	wp_enqueue_style( 'pvc-frontend' );

	$opts = get_option( PVC_OPTION_KEY, array() );
	$bg = ( isset( $opts['background'] ) && $opts['background'] === 'muted' ) ? '#f7f7f7' : 'transparent';

	$css = "
/* Product View Card - frontend */
.pvc-wrap{ width:100%; padding:20px 12px; background: {$bg}; box-sizing:border-box; }
.pvc-container{ max-width:1300px; margin:0 auto; box-sizing:border-box; }
.pvc-products{
	display:grid;
	gap:28px;
	align-items:stretch;
	width:100%;
	box-sizing:border-box;
}

/* IMPORTANT FIX */
.pvc-products > *{
	min-width:0;
}

/* columns classes */
.pvc-cols-3{ grid-template-columns:repeat(3,1fr); }
.pvc-cols-4{ grid-template-columns:repeat(4,1fr); }
.pvc-cols-5{ grid-template-columns:repeat(5,1fr); }

.pvc-card{ background:#fff; border:1px solid #e6e6e6; padding:22px; border-radius:8px; display:flex; flex-direction:column; min-height:360px; position:relative; cursor:pointer; transition:transform .12s, box-shadow .12s; }
.pvc-card:hover{ transform:translateY(-6px); box-shadow:0 10px 30px rgba(10,10,10,0.06); }
.pvc-logo-pin{ position:absolute; left:12px; top:12px; width:28px;height:28px; border-radius:6px; display:flex;align-items:center;justify-content:center; font-weight:bold;color:#f68b2f;font-size:14px; border:1px solid #f2f2f2; background:#fff; overflow:hidden; }
.pvc-logo-pin img{ max-width:100%; max-height:100%; display:block; object-fit:cover; }

/* keep design unchanged */
.pvc-product-media{ height:180px; display:flex; align-items:center; justify-content:center; padding:8px 0; }
.pvc-product-media img{ max-height:150px; max-width:100%; object-fit:contain; display:block; }
.pvc-title{ margin-top:12px; font-size:18px; line-height:1.2; color:#111; margin-bottom:10px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pvc-price-row{ text-align:left; margin-top:auto; margin-bottom:12px; }
.pvc-price{ font-size:20px; color:#111; display:inline-block; margin-right:12px; }
.pvc-price-old{ color:#9b9b9b; text-decoration:line-through; font-size:16px; }
.pvc-badge-sale{ position:absolute; right:12px; top:12px; display:flex; gap:8px; align-items:center; }
.pvc-badge-pill{ background:#b6e8dd; padding:8px 12px; border-radius:24px; font-weight:600; font-size:13px; color:#064d3b; }
.pvc-badge-circle{ background:#ff3b30; color:#fff; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; }
.pvc-btn{ display:inline-block; width:100%; text-align:center; padding:14px 18px; border-radius:8px; background:#f68b2f; color:#fff; font-size:16px; font-weight:600; text-decoration:none; border:none; cursor:pointer; box-shadow:0 6px 0 rgba(246,139,47,0.08); }
.pvc-media-frame{ border:1px solid transparent; padding:6px; border-radius:6px; background:transparent; }

.pvc-card.featured .pvc-media-frame{ border-color:#f68b2f; box-shadow:0 0 0 4px rgba(246,139,47,0.03) inset; }

/* Make card clickable but exclude button */
.pvc-card a.pvc-card-link{ position:absolute; inset:0; z-index:1; display:block; text-indent:-9999px; }
.pvc-card *{ position:relative; z-index:2; } /* keep content above the invisible link */
.pvc-quick-add{ z-index:3; }

/* responsive breakpoints */
@media (max-width:1200px){ .pvc-cols-5{ grid-template-columns:repeat(4,1fr); } .pvc-card{ min-height:380px; } }
@media (max-width:900px){ .pvc-cols-5, .pvc-cols-4{ grid-template-columns:repeat(3,1fr); } .pvc-cols-3{ grid-template-columns:repeat(2,1fr);} }
@media (max-width:600px){ .pvc-cols-5, .pvc-cols-4, .pvc-cols-3{ grid-template-columns:repeat(1,1fr);} }
";
	wp_add_inline_style( 'pvc-frontend', $css );

	// JS (unchanged)
	wp_register_script( 'pvc-frontend-js', false, array( 'jquery' ), false, true );
	wp_enqueue_script( 'pvc-frontend-js' );

	$ajax_url    = admin_url( 'admin-ajax.php' );
	$nonce_value = wp_create_nonce( PVC_NONCE );
	$opts        = get_option( PVC_OPTION_KEY, array() );
	$quick_label = isset( $opts['quick_add_label'] ) ? esc_js( $opts['quick_add_label'] ) : 'Quick Add';

	$js = <<<JS
(function($){
	var pvc = { ajaxUrl: '{$ajax_url}', nonce: '{$nonce_value}' };

	// Quick Add via AJAX

	$(document).on('click', '.pvc-quick-add', function(e){
		e.preventDefault();
		e.stopPropagation();

		var btn = $(this);
		if (btn.data('loading')) return;

		var pid = parseInt(btn.data('product_id')) || 0;
		var qty = parseInt(btn.data('quantity')) || 1;
		if (!pid) return;

		btn.data('loading', true)
		   .prop('disabled', true)
		   .text('Adding...');

		$.post(pvc.ajaxUrl, {
			action: 'pvc_quick_add',
			product_id: pid,
			quantity: qty,
			_wpnonce: pvc.nonce
		}, function(res){

			if (res && res.success) {
				btn.text('Added');

				$(document.body).trigger('added_to_cart', [
					res.data?.fragments || {},
					res.data?.cart_hash || '',
					btn
				]);
			} else {
				btn.text('Error');
			}

			setTimeout(function(){
				btn.text('{$quick_label}')
				   .prop('disabled', false)
				   .data('loading', false);
			}, 900);

		}, 'json').fail(function(){
			btn.text('Error');
			setTimeout(function(){
				btn.text('{$quick_label}')
				   .prop('disabled', false)
				   .data('loading', false);
			}, 900);
		});
	});

	$(document).on('click', '.pvc-card .pvc-quick-add', function(e){
		e.stopPropagation();
	});
    
     // HARD force remove WooCommerce "View cart" link (all cases)
$(document.body).on('added_to_cart ajaxComplete', function () {
	setTimeout(function () {
		$('.added_to_cart.wc-forward').remove();
	}, 1);
});


})(jQuery);

JS;
	wp_add_inline_script( 'pvc-frontend-js', $js );
} );

/* -----------------------
   Shortcode: [product_view_card]
   attributes:
      id (saved slug)
      per_page
      columns (3|4|5)
      category (slug(s) comma separated)
      orderby
      order
      show_sale_badge (1|0)
      full_width (1|0)
----------------------- */
add_shortcode( 'product_view_card', function( $atts ) {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return '<p>WooCommerce required.</p>';
	}

	$saved = get_option( PVC_SHORTCODES_KEY, array() );
	$opts  = get_option( PVC_OPTION_KEY, array() );

	$atts = shortcode_atts( array(
		'id'             => '',
		'per_page'       => 5,
		'columns'        => 5,
		'category'       => '',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'show_sale_badge' => '1',
		'full_width'     => '0',
	), $atts, 'product_view_card' );

	// enforce allowed columns
	$atts['columns'] = in_array( intval( $atts['columns'] ), array( 3, 4, 5 ) ) ? intval( $atts['columns'] ) : 5;

	// merge saved view if id provided
	if ( ! empty( $atts['id'] ) && isset( $saved[ $atts['id'] ] ) ) {
		$entry = $saved[ $atts['id'] ];
		// ensure columns valid
		$entry['columns'] = isset( $entry['columns'] ) && in_array( intval( $entry['columns'] ), array( 3, 4, 5 ) ) ? intval( $entry['columns'] ) : $atts['columns'];
		$atts = array_merge( $atts, $entry );
	}

	// Build query
	$args = array(
		'post_type'      => 'product',
		'posts_per_page' => intval( $atts['per_page'] ),
		'orderby'        => sanitize_text_field( $atts['orderby'] ),
		'order'          => sanitize_text_field( $atts['order'] ),
		'post_status'    => 'publish',
	);

	if ( ! empty( $atts['category'] ) ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => array_map( 'trim', explode( ',', $atts['category'] ) ),
			),
		);
	}

	$q = new WP_Query( $args );

	ob_start();

	$wrap      = 'pvc-wrap';
	$container = 'pvc-container';
	if ( intval( $atts['full_width'] ) === 1 ) {
		$container .= ' pvc-fullwidth';
	}
	$cols_class = 'pvc-cols-' . intval( $atts['columns'] );

	// get logo image if set
	$options = get_option( PVC_OPTION_KEY, array() );
	$logo_id = isset( $options['logo_image_id'] ) ? intval( $options['logo_image_id'] ) : 0;
	$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';

	?>
	<div class="<?php echo esc_attr( $wrap ); ?>">
		<div class="<?php echo esc_attr( $container ); ?>">
			<div class="pvc-products <?php echo esc_attr( $cols_class ); ?>">
				<?php
				if ( $q->have_posts() ) :
					while ( $q->have_posts() ) : $q->the_post();
						global $product;
						if ( ! $product || ! is_object( $product ) ) {
							$product = wc_get_product( get_the_ID() );
						}
						$pid       = $product->get_id();
						$title     = get_the_title();
						$permalink = get_permalink( $pid );
						$image     = wp_get_attachment_image_src( $product->get_image_id(), 'medium' );
						$img_url   = $image ? $image[0] : wc_placeholder_img_src();
						$is_featured = get_post_meta( $pid, 'pvc_featured', true ) === '1';
						$on_sale     = $product->is_on_sale();
						$regular     = $product->get_regular_price();
						$sale_price  = $product->get_sale_price();
						$discount_amount = 0;
						if ( $sale_price && $regular ) {
							$discount_amount = floatval( $regular ) - floatval( $sale_price );
						}

						?>
						<article class="pvc-card <?php echo $is_featured ? 'featured' : ''; ?>" data-product-id="<?php echo esc_attr( $pid ); ?>">
							<a class="pvc-card-link" href="<?php echo esc_url( $permalink ); ?>">View <?php echo esc_attr( $title ); ?></a>

							<div class="pvc-logo-pin">
								<?php if ( $logo_url ) : ?>
									<img src="<?php echo esc_url( $logo_url ); ?>" alt="logo" />
								<?php else: ?>
									<span style="font-weight:700;color:#f68b2f;">y</span>
								<?php endif; ?>
							</div>

							<?php if ( '1' === $atts['show_sale_badge'] && $on_sale ) : ?>
								<div class="pvc-badge-sale">
									<div class="pvc-badge-pill">ON SALE</div>
									<?php if ( $discount_amount > 0 ) : ?>
										<div class="pvc-badge-circle"><?php echo 'Save ' . wp_kses_post( wc_price( $discount_amount ) ); ?></div>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<div class="pvc-product-media">
								<div class="pvc-media-frame">
									<a href="<?php echo esc_url( $permalink ); ?>"><img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" /></a>
								</div>
							</div>

							<h3 class="pvc-title"><a href="<?php echo esc_url( $permalink ); ?>" style="color:inherit;text-decoration:none;"><?php echo esc_html( $title ); ?></a></h3>

							<div class="pvc-price-row">
								<?php
								// Use wp_kses_post to allow wc_price HTML but prevent arbitrary tags.
								if ( $product->is_on_sale() && $product->get_regular_price() ) {
									echo '<span class="pvc-price">' . wp_kses_post( wc_price( $product->get_price() ) ) . '</span>';
									echo '<span class="pvc-price-old">' . wp_kses_post( wc_price( $product->get_regular_price() ) ) . '</span>';
								} else {
									echo '<span class="pvc-price">' . wp_kses_post( wc_price( $product->get_price() ) ) . '</span>';
								}
								?>
							</div>

							<?php
							$button_disabled = ! $product->is_purchasable() || ! $product->is_in_stock();
							$label = esc_html( get_option( PVC_OPTION_KEY, array() )['quick_add_label'] ?? 'Quick Add' );
							?>
							<button class="pvc-btn pvc-quick-add" data-product_id="<?php echo esc_attr( $pid ); ?>" data-quantity="1" <?php echo $button_disabled ? 'disabled' : ''; ?>><?php echo $label; ?></button>
						</article>
						<?php
					endwhile;
					wp_reset_postdata();
				else:
					echo '<p>No products found.</p>';
				endif;
				?>
			</div>
		</div>
	</div>
	<?php

	return ob_get_clean();
} );

/* -----------------------
   AJAX Quick Add handler
   ----------------------- */
add_action( 'wp_ajax_pvc_quick_add', 'pvc_quick_add_handler' );
add_action( 'wp_ajax_nopriv_pvc_quick_add', 'pvc_quick_add_handler' );
function pvc_quick_add_handler() {
	check_ajax_referer( PVC_NONCE, '_wpnonce' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		wp_send_json_error( array( 'message' => 'WooCommerce required' ) );
	}

	$pid = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	$qty = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

	if ( ! $pid ) {
		wp_send_json_error( array( 'message' => 'Invalid product' ) );
	}

	if ( WC()->cart->add_to_cart( $pid, $qty ) ) {

	WC()->cart->calculate_totals();

	ob_start();
	woocommerce_mini_cart();
	$mini_cart = ob_get_clean();

	wp_send_json_success( array(
		'fragments' => apply_filters(
			'woocommerce_add_to_cart_fragments',
			array(
				'div.widget_shopping_cart_content' => $mini_cart,
			)
		),
		'cart_hash' => WC()->cart->get_cart_hash(),
	) );
}

wp_send_json_error();


}

/* -----------------------
   Admin: menu & pages
   - Settings (Quick Add label, background, logo upload)
   - Saved Views manager (create/edit/delete)
----------------------- */
add_action( 'admin_menu', function() {
	add_menu_page( 'Product View Card', 'Product View Card', 'manage_options', 'pvc_manager', 'pvc_admin_page', 'dashicons-visibility', 55 );
} );

add_action( 'admin_enqueue_scripts', function( $hook ) {
	// Load media scripts for upload button on plugin admin page
	if ( strpos( $hook, 'pvc_manager' ) !== false || $hook === 'toplevel_page_pvc_manager' ) {
		wp_enqueue_media();
		wp_add_inline_script( 'jquery', '
			// small helper to open media uploader when clicking upload button
			jQuery(document).ready(function($){
				$(document).on("click", ".pvc-upload-btn", function(e){
					e.preventDefault();
					var button = $(this);
					var field = $("#" + button.data("target"));
					var preview = $("#" + button.data("preview"));
					var frame = wp.media({
						title: "Select Logo Image",
						button: { text: "Use image" },
						multiple: false
					});
					frame.on("select", function(){
						var attachment = frame.state().get("selection").first().toJSON();
						field.val(attachment.id);
						preview.attr("src", attachment.url).show();
					});
					frame.open();
				});
				$(document).on("click", ".pvc-remove-logo", function(e){
					e.preventDefault();
					var button = $(this);
					var field = $("#" + button.data("target"));
					var preview = $("#" + button.data("preview"));
					field.val(0);
					preview.attr("src","").hide();
				});
			});
		' );
	}
	// small admin CSS tweaks retained
	if ( strpos( $hook, 'pvc_manager' ) !== false || $hook === 'toplevel_page_pvc_manager' ) {
		wp_add_inline_style( 'wp-admin', '.wrap h1{margin-bottom:8px;} .wrap .form-table th{width:150px;} .wrap .updated,.wrap .error{max-width:1200px}' );
	}
} );

function pvc_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// fetch options + saved views
	$options = get_option( PVC_OPTION_KEY, array() );
	$saved   = get_option( PVC_SHORTCODES_KEY, array() );
	$cats    = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );

	// handle form submissions (create / update / delete / save options)
	if ( isset( $_POST['pvc_action'] ) && check_admin_referer( 'pvc_admin_save', 'pvc_admin_nonce' ) ) {
		$action = sanitize_text_field( $_POST['pvc_action'] );

		if ( 'save_options' === $action ) {
			// Save Quick Add label, background, and logo image ID
			$options['quick_add_label'] = sanitize_text_field( $_POST['quick_add_label'] ?? 'Quick Add' );
			$options['background']      = in_array( $_POST['background'] ?? 'transparent', array( 'transparent', 'muted' ) ) ? $_POST['background'] : 'transparent';
			$options['logo_image_id']   = isset( $_POST['logo_image_id'] ) ? intval( $_POST['logo_image_id'] ) : 0;
			update_option( PVC_OPTION_KEY, $options );
			echo '<div class="updated"><p>Settings saved.</p></div>';
		}

		if ( 'create_view' === $action || 'update_view' === $action ) {
			$saved = get_option( PVC_SHORTCODES_KEY, array() );
			$name  = sanitize_text_field( $_POST['view_name'] ?? '' );
			$slug  = sanitize_title( $_POST['view_slug'] ?? $name );

			// For update, slug must exist
			if ( 'create_view' === $action ) {
				if ( empty( $slug ) ) {
					$slug = 'view-' . time();
				}
				if ( isset( $saved[ $slug ] ) ) {
					echo '<div class="error"><p>Slug already exists. Choose a different one.</p></div>';
				} else {
					$entry = array(
						'name'            => $name,
						'per_page'        => intval( $_POST['view_per_page'] ?? 5 ),
						'category'        => sanitize_text_field( $_POST['view_category'] ?? '' ),
						'orderby'         => sanitize_text_field( $_POST['view_orderby'] ?? 'date' ),
						'order'           => sanitize_text_field( $_POST['view_order'] ?? 'DESC' ),
						'show_sale_badge' => isset( $_POST['view_show_sale_badge'] ) ? '1' : '0',
						'full_width'      => isset( $_POST['view_full_width'] ) ? '1' : '0',
						'columns'         => in_array( intval( $_POST['view_columns'] ?? 5 ), array( 3, 4, 5 ) ) ? intval( $_POST['view_columns'] ) : 5,
					);
					$saved[ $slug ] = $entry;
					update_option( PVC_SHORTCODES_KEY, $saved );
					echo '<div class="updated"><p>Saved view created. Slug: <code>' . esc_html( $slug ) . '</code></p></div>';
				}
			} else { // update_view
				$orig_slug = sanitize_text_field( $_POST['orig_view_slug'] ?? '' );
				if ( empty( $orig_slug ) || ! isset( $saved[ $orig_slug ] ) ) {
					echo '<div class="error"><p>Invalid view to update.</p></div>';
				} else {
					// update fields (slug remains the same)
					$saved[ $orig_slug ] = array(
						'name'            => $name,
						'per_page'        => intval( $_POST['view_per_page'] ?? 5 ),
						'category'        => sanitize_text_field( $_POST['view_category'] ?? '' ),
						'orderby'         => sanitize_text_field( $_POST['view_orderby'] ?? 'date' ),
						'order'           => sanitize_text_field( $_POST['view_order'] ?? 'DESC' ),
						'show_sale_badge' => isset( $_POST['view_show_sale_badge'] ) ? '1' : '0',
						'full_width'      => isset( $_POST['view_full_width'] ) ? '1' : '0',
						'columns'         => in_array( intval( $_POST['view_columns'] ?? 5 ), array( 3, 4, 5 ) ) ? intval( $_POST['view_columns'] ) : 5,
					);
					update_option( PVC_SHORTCODES_KEY, $saved );
					echo '<div class="updated"><p>Saved view updated: <code>' . esc_html( $orig_slug ) . '</code></p></div>';
				}
			}
		}

		if ( 'delete_view' === $action ) {
			$slug  = sanitize_text_field( $_POST['delete_slug'] ?? '' );
			$saved = get_option( PVC_SHORTCODES_KEY, array() );
			if ( isset( $saved[ $slug ] ) ) {
				unset( $saved[ $slug ] );
				update_option( PVC_SHORTCODES_KEY, $saved );
				echo '<div class="updated"><p>Deleted view: ' . esc_html( $slug ) . '</p></div>';
			}
		}
	}

	// If editing a view, load its data into the form
	$edit_slug = isset( $_GET['edit_view'] ) ? sanitize_text_field( $_GET['edit_view'] ) : '';
	$edit_data = null;
	if ( $edit_slug && isset( $saved[ $edit_slug ] ) ) {
		$edit_data = $saved[ $edit_slug ];
		$edit_data['slug'] = $edit_slug;
	}

	// re-fetch sets after possible updates
	$options = get_option( PVC_OPTION_KEY, array() );
	$saved   = get_option( PVC_SHORTCODES_KEY, array() );
	$cats    = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );

	?>
	<div class="wrap" style="max-width:1200px;">
		<h1 style="display:flex;align-items:center;gap:12px;"><span style="font-size:20px;">Product View Card</span> <span style="color:#888;font-size:13px;">— views & settings</span></h1>

		<div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:18px;">
			<!-- Settings box -->
			<div style="flex:1;min-width:360px;max-width:760px;background:#fff;border:1px solid #e7e7e7;padding:20px;border-radius:10px;">
				<h2 style="margin-top:0">Settings</h2>
				<form method="post">
					<?php wp_nonce_field( 'pvc_admin_save', 'pvc_admin_nonce' ); ?>
					<input type="hidden" name="pvc_action" value="save_options" />
					<table class="form-table">
						<tr>
							<th>Quick Add Label</th>
							<td><input type="text" name="quick_add_label" value="<?php echo esc_attr( $options['quick_add_label'] ?? 'Quick Add' ); ?>" class="regular-text" /></td>
						</tr>
						<tr>
							<th>Background</th>
							<td>
								<label><input type="radio" name="background" value="transparent" <?php checked( $options['background'] ?? 'transparent', 'transparent' ); ?> /> Transparent</label><br/>
								<label><input type="radio" name="background" value="muted" <?php checked( $options['background'] ?? 'transparent', 'muted' ); ?> /> Muted (light gray)</label>
							</td>
						</tr>

						<tr>
							<th>Top-left logo (replaces &quot;y&quot;)</th>
							<td>
								<?php
								$logo_id = isset( $options['logo_image_id'] ) ? intval( $options['logo_image_id'] ) : 0;
								$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
								?>
								<input type="hidden" id="logo_image_id" name="logo_image_id" value="<?php echo esc_attr( $logo_id ); ?>" />
								<img id="logo_preview" src="<?php echo esc_url( $logo_url ); ?>" alt="" style="<?php echo $logo_url ? '' : 'display:none;'; ?>max-width:60px;border:1px solid #ddd;padding:4px;border-radius:6px;margin-bottom:8px;" />
								<br />
								<button class="button pvc-upload-btn" data-target="logo_image_id" data-preview="logo_preview">Upload / Select Image</button>
								<button class="button pvc-remove-logo" data-target="logo_image_id" data-preview="logo_preview" style="margin-left:8px;">Remove</button>
								<p class="description">Small square image recommended (e.g. 64×64). Will replace the 'y' text in the card top-left.</p>
							</td>
						</tr>

					</table>
					<?php submit_button( 'Save Settings' ); ?>
				</form>
			</div>

			<!-- Create / Edit View box -->
			<div style="width:380px;min-width:300px;background:#fff;border:1px solid #e7e7e7;padding:18px;border-radius:10px;">
				<?php if ( $edit_data ): ?>
					<h2 style="margin-top:0;">Edit View: <?php echo esc_html( $edit_data['name'] ); ?></h2>
				<?php else: ?>
					<h2 style="margin-top:0;">Create New View</h2>
				<?php endif; ?>

				<form method="post">
					<?php wp_nonce_field( 'pvc_admin_save', 'pvc_admin_nonce' ); ?>

					<?php if ( $edit_data ): ?>
						<input type="hidden" name="pvc_action" value="update_view" />
						<input type="hidden" name="orig_view_slug" value="<?php echo esc_attr( $edit_data['slug'] ); ?>" />
					<?php else: ?>
						<input type="hidden" name="pvc_action" value="create_view" />
					<?php endif; ?>

					<table class="form-table">
						<tr>
							<th>Name</th>
							<td><input type="text" name="view_name" required class="regular-text" value="<?php echo esc_attr( $edit_data['name'] ?? '' ); ?>" /></td>
						</tr>

						<?php if ( ! $edit_data ): // slug only on create ?>
							<tr>
								<th>Slug</th>
								<td><input type="text" name="view_slug" class="regular-text" placeholder="optional - autogenerated if blank" /></td>
							</tr>
						<?php else: ?>
							<tr>
								<th>Slug</th>
								<td><code><?php echo esc_html( $edit_data['slug'] ); ?></code> (cannot change)</td>
							</tr>
						<?php endif; ?>

						<tr><th>Per page</th><td><input type="number" name="view_per_page" value="<?php echo esc_attr( $edit_data['per_page'] ?? 5 ); ?>" min="1" class="small-text" /></td></tr>

						<tr>
							<th>Category</th>
							<td>
								<select name="view_category">
									<option value="">All categories</option>
									<?php foreach ( $cats as $c ): ?>
										<option value="<?php echo esc_attr( $c->slug ); ?>" <?php selected( $edit_data['category'] ?? '', $c->slug ); ?>><?php echo esc_html( $c->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th>Columns</th>
							<td>
								<select name="view_columns">
									<option value="3" <?php selected( $edit_data['columns'] ?? 5, 3 ); ?>>3</option>
									<option value="4" <?php selected( $edit_data['columns'] ?? 5, 4 ); ?>>4</option>
									<option value="5" <?php selected( $edit_data['columns'] ?? 5, 5 ); ?>>5</option>
								</select>
							</td>
						</tr>

						<tr><th>Full width</th><td><label><input type="checkbox" name="view_full_width" <?php checked( $edit_data['full_width'] ?? '0', '1' ); ?> /> Full width (no max container)</label></td></tr>
						<tr><th>Sale badge</th><td><label><input type="checkbox" name="view_show_sale_badge" <?php checked( $edit_data['show_sale_badge'] ?? '1', '1' ); ?> /> Show sale badge</label></td></tr>

					</table>

					<?php if ( $edit_data ): ?>
						<?php submit_button( 'Update View' ); ?>
					<?php else: ?>
						<?php submit_button( 'Create View' ); ?>
					<?php endif; ?>
				</form>

				<?php if ( $edit_data ): ?>
					<form method="post" style="margin-top:12px;">
						<?php wp_nonce_field( 'pvc_admin_save', 'pvc_admin_nonce' ); ?>
						<input type="hidden" name="pvc_action" value="delete_view" />
						<input type="hidden" name="delete_slug" value="<?php echo esc_attr( $edit_data['slug'] ); ?>" />
						<button class="button button-secondary" onclick="return confirm('Delete this view?');">Delete this view</button>
					</form>
				<?php endif; ?>
			</div>
		</div>

		<h2 style="margin-top:26px;">Saved Views</h2>
		<p>Insert in pages: <code>[product_view_card id="slug"]</code> or ad-hoc: <code>[product_view_card per_page="6" columns="4" category="oils"]</code>.</p>

		<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px;margin-top:12px;">
			<?php if ( ! empty( $saved ) ): foreach ( $saved as $slug => $entry ): ?>
				<div style="background:#fff;border:1px solid #e7e7e7;padding:14px;border-radius:10px;box-shadow:0 6px 18px rgba(12,12,12,0.03);">
					<div style="display:flex;justify-content:space-between;align-items:start;gap:12px;">
						<div>
							<h3 style="margin:0 0 6px 0;"><?php echo esc_html( $entry['name'] ); ?></h3>
							<div style="color:#666;font-size:13px;">Slug: <code><?php echo esc_html( $slug ); ?></code></div>
						</div>
						<div style="text-align:right">
							<div style="font-weight:700;font-size:18px;color:#333;"><?php echo intval( $entry['per_page'] ); ?> items</div>
							<div style="color:#999;font-size:13px;"><?php echo intval( $entry['columns'] ); ?> cols</div>
						</div>
					</div>

					<div style="margin-top:10px;color:#444;font-size:13px;">
						<div>Category: <?php echo esc_html( $entry['category'] ?: 'All' ); ?></div>
						<div>Order: <?php echo esc_html( $entry['orderby'] . ' ' . $entry['order'] ); ?></div>
						<div>Sale badge: <?php echo $entry['show_sale_badge'] === '1' ? 'Yes' : 'No'; ?> | Full width: <?php echo $entry['full_width'] === '1' ? 'Yes' : 'No'; ?></div>
					</div>

					<div style="display:flex;gap:8px;margin-top:12px;">
						<!-- EDIT button opens the manager with ?edit_view=slug to load into the edit form -->
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=pvc_manager&edit_view=' . urlencode( $slug ) ) ); ?>">Edit</a>

						<form method="post" style="display:inline-block;">
							<?php wp_nonce_field( 'pvc_admin_save', 'pvc_admin_nonce' ); ?>
							<input type="hidden" name="pvc_action" value="delete_view" />
							<input type="hidden" name="delete_slug" value="<?php echo esc_attr( $slug ); ?>" />
							<button class="button button-secondary" onclick="return confirm('Delete this saved view?');">Delete</button>
						</form>
					</div>
				</div>
			<?php endforeach; else: ?>
				<div style="grid-column:1/-1;background:#fff;border:1px dashed #ddd;padding:20px;border-radius:8px;text-align:center;">No saved views yet. Create one using the form on the right.</div>
			<?php endif; ?>
		</div>

	</div>
	<?php
}

/* -----------------------
   Admin helper: show notice when opening new page with ?shortcode=slug
   ----------------------- */
add_action( 'load-post-new.php', function() {
	if ( isset( $_GET['shortcode'] ) && current_user_can( 'edit_posts' ) ) {
		add_action( 'admin_notices', function() {
			$slug = sanitize_text_field( $_GET['shortcode'] );
			echo '<div class="notice notice-success is-dismissible"><p>Shortcode <code>[product_view_card id="' . esc_html( $slug ) . '"]</code> — paste into this new page or use the Shortcode block.</p></div>';
		} );
	}
} );

/* -----------------------
   End of plugin file
   ----------------------- */