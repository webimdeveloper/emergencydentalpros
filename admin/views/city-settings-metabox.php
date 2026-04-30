<?php
/**
 * Location page settings metabox for edp_seo_city CPT.
 *
 * Shows all city-landing template fields with resolved template defaults
 * as placeholders. Empty = inherit from template. Filled = page-specific override.
 *
 * @package EmergencyDentalPros
 *
 * @var WP_Post $post
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load the linked location row so we can resolve template placeholders.
$location_id = (int) get_post_meta( $post->ID, '_edp_location_id', true );
$row         = $location_id > 0 ? EDP_Database::get_row_by_id( $location_id ) : null;

$settings  = EDP_Settings::get_all();
$templates = $settings['templates']['city_landing'] ?? [];
$base      = EDP_Template_Engine::base_vars();
$vars      = $row ? EDP_Template_Engine::context_from_city_row( $base, $row ) : $base;

// Resolved template defaults — shown as input placeholders.
$ph_meta_title    = EDP_Template_Engine::replace( (string) ( $templates['meta_title']       ?? '' ), $vars );
$ph_h1            = EDP_Template_Engine::replace( (string) ( $templates['h1']               ?? '' ), $vars );
$ph_meta_desc     = EDP_Template_Engine::replace( (string) ( $templates['meta_description'] ?? '' ), $vars );
$ph_body          = EDP_Template_Engine::replace( (string) ( $templates['body']             ?? '' ), $vars );
$ph_comm_h2       = EDP_Template_Engine::replace( (string) ( $templates['communities_h2']   ?? '' ), $vars );
$ph_comm_body     = EDP_Template_Engine::replace( (string) ( $templates['communities_body'] ?? '' ), $vars );
$ph_other_h2      = EDP_Template_Engine::replace( (string) ( $templates['other_cities_h2']  ?? '' ), $vars );

// Saved per-page overrides.
$val_meta_title   = (string) get_post_meta( $post->ID, '_edp_meta_title',       true );
$val_h1           = (string) get_post_meta( $post->ID, '_edp_h1',               true );
$val_meta_desc    = (string) get_post_meta( $post->ID, '_edp_meta_description', true );
$val_body         = (string) get_post_meta( $post->ID, '_edp_body',             true );
$val_comm_h2      = (string) get_post_meta( $post->ID, '_edp_communities_h2',   true );
$val_comm_body    = (string) get_post_meta( $post->ID, '_edp_communities_body', true );
$val_other_h2     = (string) get_post_meta( $post->ID, '_edp_other_cities_h2',  true );

// Front-end page URL.
$page_url = '';
if ( $row ) {
    $page_url = home_url( user_trailingslashit(
        'locations/' . rawurlencode( (string) ( $row['state_slug'] ?? '' ) )
        . '/' . rawurlencode( (string) ( $row['city_slug'] ?? '' ) )
    ) );
}

wp_nonce_field( 'edp_location_settings_' . $post->ID, 'edp_location_settings_nonce' );
?>

<?php if ( $page_url !== '' ) : ?>
<div class="edp-mb-page-link">
    <span class="dashicons dashicons-admin-site-alt3"></span>
    <a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener">
        <?php echo esc_html( $page_url ); ?>
    </a>
</div>
<?php endif; ?>

<p class="edp-mb-global-note">
    <?php esc_html_e( 'Leave any field blank to inherit from the global City Landing template.', 'emergencydentalpros' ); ?>
</p>

<div class="edp-mb-section-title"><?php esc_html_e( 'SEO', 'emergencydentalpros' ); ?></div>

<div class="edp-mb-row">
    <label for="edp_meta_title"><?php esc_html_e( 'Meta title', 'emergencydentalpros' ); ?></label>
    <input type="text" name="edp_meta_title" id="edp_meta_title"
        value="<?php echo esc_attr( $val_meta_title ); ?>"
        placeholder="<?php echo esc_attr( $ph_meta_title ); ?>" />
    <p class="edp-mb-hint"><?php esc_html_e( 'Overrides the &lt;title&gt; tag for this page only.', 'emergencydentalpros' ); ?></p>
</div>

<div class="edp-mb-row">
    <label for="edp_meta_description"><?php esc_html_e( 'Meta description', 'emergencydentalpros' ); ?></label>
    <textarea name="edp_meta_description" id="edp_meta_description" rows="2"
        placeholder="<?php echo esc_attr( $ph_meta_desc ); ?>"><?php echo esc_textarea( $val_meta_desc ); ?></textarea>
    <p class="edp-mb-hint"><?php esc_html_e( 'Overrides the meta description tag for this page only.', 'emergencydentalpros' ); ?></p>
</div>

<div class="edp-mb-section-title"><?php esc_html_e( 'Page headings', 'emergencydentalpros' ); ?></div>

<div class="edp-mb-row">
    <label for="edp_h1"><?php esc_html_e( 'H1', 'emergencydentalpros' ); ?></label>
    <input type="text" name="edp_h1" id="edp_h1"
        value="<?php echo esc_attr( $val_h1 ); ?>"
        placeholder="<?php echo esc_attr( $ph_h1 ); ?>" />
</div>

<div class="edp-mb-section-title"><?php esc_html_e( 'Main content', 'emergencydentalpros' ); ?></div>

<div class="edp-mb-row">
    <label><?php esc_html_e( 'Body text', 'emergencydentalpros' ); ?></label>
    <?php if ( $ph_body !== '' ) : ?>
        <p class="edp-mb-hint" style="margin-bottom:6px;">
            <?php esc_html_e( 'Template default:', 'emergencydentalpros' ); ?>
            <em><?php echo esc_html( wp_strip_all_tags( $ph_body ) ); ?></em>
        </p>
    <?php endif; ?>
    <?php
    wp_editor(
        $val_body,
        'edp_body',
        [
            'textarea_name' => 'edp_body',
            'media_buttons' => false,
            'textarea_rows' => 6,
            'teeny'         => true,
        ]
    );
    ?>
</div>

<div class="edp-mb-section-title"><?php esc_html_e( 'Communities section', 'emergencydentalpros' ); ?></div>

<div class="edp-mb-row">
    <label for="edp_communities_h2"><?php esc_html_e( 'H2', 'emergencydentalpros' ); ?></label>
    <input type="text" name="edp_communities_h2" id="edp_communities_h2"
        value="<?php echo esc_attr( $val_comm_h2 ); ?>"
        placeholder="<?php echo esc_attr( $ph_comm_h2 ); ?>" />
</div>

<div class="edp-mb-row">
    <label><?php esc_html_e( 'Body text', 'emergencydentalpros' ); ?></label>
    <?php if ( $ph_comm_body !== '' ) : ?>
        <p class="edp-mb-hint" style="margin-bottom:6px;">
            <?php esc_html_e( 'Template default:', 'emergencydentalpros' ); ?>
            <em><?php echo esc_html( wp_strip_all_tags( $ph_comm_body ) ); ?></em>
        </p>
    <?php endif; ?>
    <?php
    wp_editor(
        $val_comm_body,
        'edp_communities_body',
        [
            'textarea_name' => 'edp_communities_body',
            'media_buttons' => false,
            'textarea_rows' => 4,
            'teeny'         => true,
        ]
    );
    ?>
</div>

<div class="edp-mb-section-title"><?php esc_html_e( 'Other cities section', 'emergencydentalpros' ); ?></div>

<div class="edp-mb-row">
    <label for="edp_other_cities_h2"><?php esc_html_e( 'H2', 'emergencydentalpros' ); ?></label>
    <input type="text" name="edp_other_cities_h2" id="edp_other_cities_h2"
        value="<?php echo esc_attr( $val_other_h2 ); ?>"
        placeholder="<?php echo esc_attr( $ph_other_h2 ); ?>" />
</div>
