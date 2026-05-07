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

// Resolved template defaults.
$ph_meta_title = EDP_Template_Engine::replace( (string) ( $templates['meta_title']       ?? '' ), $vars );
$ph_h1         = EDP_Template_Engine::replace( (string) ( $templates['h1']               ?? '' ), $vars );
$ph_meta_desc  = EDP_Template_Engine::replace( (string) ( $templates['meta_description'] ?? '' ), $vars );
$ph_comm_body  = EDP_Template_Engine::replace( (string) ( $templates['communities_body'] ?? '' ), $vars );

// Communities H2: fall back to raw template if county_name is empty in this row.
$ph_comm_h2 = EDP_Template_Engine::replace( (string) ( $templates['communities_h2'] ?? '' ), $vars );
if ( $ph_comm_h2 === '' ) {
    $ph_comm_h2 = (string) ( $templates['communities_h2'] ?? '' );
}

// Saved per-page overrides.
$val_meta_title   = (string) get_post_meta( $post->ID, '_edp_meta_title',       true );
$val_h1           = (string) get_post_meta( $post->ID, '_edp_h1',               true );
$val_meta_desc    = (string) get_post_meta( $post->ID, '_edp_meta_description', true );
$val_comm_h2      = (string) get_post_meta( $post->ID, '_edp_communities_h2',   true );
$val_comm_body    = (string) get_post_meta( $post->ID, '_edp_communities_body', true );

// Other cities show/hide toggle (default 1 = shown).
$show_other_cities_raw = get_post_meta( $post->ID, '_edp_show_other_cities', true );
$show_other_cities     = $show_other_cities_raw === '' ? 1 : (int) $show_other_cities_raw;

// Front-end page URL.
$page_url = '';
if ( $row ) {
    $page_url = EDP_Rewrite::city_url( $row );
}

// Field coverage count (other_cities toggle counts when explicitly set to 0).
$all_overrides = [ $val_meta_title, $val_meta_desc, $val_h1, $val_comm_h2, $val_comm_body ];
$filled_count  = count( array_filter( $all_overrides, fn( $v ) => $v !== '' ) );
if ( $show_other_cities_raw !== '' ) {
    $filled_count++;
}
$total_count = count( $all_overrides ) + 1; // +1 for other_cities toggle

wp_nonce_field( 'edp_location_settings_' . $post->ID, 'edp_location_settings_nonce' );
?>

<div class="edp-mb-city-title">
    <span class="dashicons dashicons-location"></span>
    <?php echo esc_html( $post->post_title ); ?>
    <span class="edp-mb-coverage-badge"><?php echo esc_html( "$filled_count / $total_count" ); ?> <?php esc_html_e( 'fields customized', 'emergencydentalpros' ); ?></span>
</div>

<?php if ( $row ) : ?>
<div class="edp-mb-location-bar">
    <?php if ( ! empty( $row['city_name'] ) ) : ?>
        <span><strong><?php echo esc_html( $row['city_name'] ); ?></strong></span>
    <?php endif; ?>
    <?php if ( ! empty( $row['state_name'] ) ) : ?>
        <span><?php echo esc_html( $row['state_name'] ); ?></span>
    <?php endif; ?>
    <?php if ( ! empty( $row['county_name'] ) ) : ?>
        <span><?php echo esc_html( $row['county_name'] ); ?></span>
    <?php endif; ?>
    <?php if ( ! empty( $row['main_zip'] ) ) : ?>
        <span>ZIP: <?php echo esc_html( $row['main_zip'] ); ?></span>
    <?php endif; ?>
    <span><?php esc_html_e( 'Location ID', 'emergencydentalpros' ); ?> #<?php echo esc_html( (string) $location_id ); ?></span>
</div>
<?php endif; ?>

<?php if ( $page_url !== '' ) : ?>
<div class="edp-mb-page-link">
    <span class="dashicons dashicons-admin-site-alt3"></span>
    <a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener">
        <?php echo esc_html( $page_url ); ?>
    </a>
</div>
<p class="edp-mb-url-note"><?php esc_html_e( 'Page URL is determined by the location row slug — not this post\'s slug.', 'emergencydentalpros' ); ?></p>
<?php endif; ?>

<?php
$redirect_post_id = (int) get_post_meta( $post->ID, '_edp_redirect_post_id', true );
if ( $redirect_post_id > 0 ) {
    $redirect_post = get_post( $redirect_post_id );
    if ( $redirect_post instanceof WP_Post ) {
        $old_url = get_permalink( $redirect_post_id ) ?: '#';
        echo '<div class="edp-mb-page-link">';
        echo '<span class="dashicons dashicons-randomize"></span> ';
        esc_html_e( 'Redirect from:', 'emergencydentalpros' );
        echo ' <a href="' . esc_url( $old_url ) . '" target="_blank" rel="noopener">';
        echo esc_html( $redirect_post->post_title . ' (#' . $redirect_post_id . ')' );
        echo '</a></div>';
    }
}
?>

<p class="edp-mb-global-note">
    <?php esc_html_e( 'Leave any field blank to inherit from the global City Landing template.', 'emergencydentalpros' ); ?>
</p>

<div class="edp-mb-section-title">
    <?php esc_html_e( 'SEO', 'emergencydentalpros' ); ?>
    <span class="edp-mb-cqs-note"><?php esc_html_e( 'up to 20 CQS points', 'emergencydentalpros' ); ?></span>
</div>

<div class="edp-mb-row">
    <label for="edp_meta_title">
        <?php esc_html_e( 'Meta title', 'emergencydentalpros' ); ?>
        <span class="edp-mb-tip" data-tip="<?php esc_attr_e( 'Recommended: 50–60 characters. Earns up to 10 CQS points when set.', 'emergencydentalpros' ); ?>">ⓘ</span>
    </label>
    <input type="text" name="edp_meta_title" id="edp_meta_title"
        value="<?php echo esc_attr( $val_meta_title ); ?>"
        placeholder="<?php echo esc_attr( $ph_meta_title ); ?>" />
    <span class="edp-mb-counter" data-for="edp_meta_title"></span>
    <p class="edp-mb-hint"><?php esc_html_e( 'Overrides the &lt;title&gt; tag for this page only.', 'emergencydentalpros' ); ?></p>
</div>

<div class="edp-mb-row">
    <label for="edp_meta_description">
        <?php esc_html_e( 'Meta description', 'emergencydentalpros' ); ?>
        <span class="edp-mb-tip" data-tip="<?php esc_attr_e( 'Recommended: 130–160 characters. Earns up to 10 CQS points when set.', 'emergencydentalpros' ); ?>">ⓘ</span>
    </label>
    <textarea name="edp_meta_description" id="edp_meta_description" rows="2"
        placeholder="<?php echo esc_attr( $ph_meta_desc ); ?>"><?php echo esc_textarea( $val_meta_desc ); ?></textarea>
    <span class="edp-mb-counter" data-for="edp_meta_description"></span>
    <p class="edp-mb-hint"><?php esc_html_e( 'Overrides the meta description tag for this page only.', 'emergencydentalpros' ); ?></p>
</div>

<div class="edp-mb-section-title">
    <?php esc_html_e( 'Page headings', 'emergencydentalpros' ); ?>
    <span class="edp-mb-cqs-note"><?php esc_html_e( 'up to 15 CQS points', 'emergencydentalpros' ); ?></span>
</div>

<div class="edp-mb-row">
    <label for="edp_h1">
        <?php esc_html_e( 'H1', 'emergencydentalpros' ); ?>
        <span class="edp-mb-tip" data-tip="<?php esc_attr_e( 'Should include the city name. Earns up to 8 CQS points when set.', 'emergencydentalpros' ); ?>">ⓘ</span>
    </label>
    <input type="text" name="edp_h1" id="edp_h1"
        value="<?php echo esc_attr( $val_h1 ); ?>"
        placeholder="<?php echo esc_attr( $ph_h1 ); ?>" />
    <span class="edp-mb-counter" data-for="edp_h1"></span>
</div>

<div class="edp-mb-section-title"><?php esc_html_e( 'Communities section', 'emergencydentalpros' ); ?></div>

<div class="edp-mb-row">
    <label for="edp_communities_h2">
        <?php esc_html_e( 'H2', 'emergencydentalpros' ); ?>
        <span class="edp-mb-tip" data-tip="<?php esc_attr_e( 'Heading for the communities section. Override to localise for this city.', 'emergencydentalpros' ); ?>">ⓘ</span>
    </label>
    <input type="text" name="edp_communities_h2" id="edp_communities_h2"
        value="<?php echo esc_attr( $val_comm_h2 ); ?>"
        placeholder="<?php echo esc_attr( $ph_comm_h2 ); ?>" />
    <span class="edp-mb-counter" data-for="edp_communities_h2"></span>
</div>

<div class="edp-mb-row">
    <label for="edp_communities_body"><?php esc_html_e( 'Body text', 'emergencydentalpros' ); ?></label>
    <textarea name="edp_communities_body" id="edp_communities_body" rows="3"
        placeholder="<?php echo esc_attr( wp_strip_all_tags( $ph_comm_body ) ); ?>"><?php echo esc_textarea( $val_comm_body ); ?></textarea>
    <p class="edp-mb-hint"><?php esc_html_e( 'Plain text with links — no rich formatting needed.', 'emergencydentalpros' ); ?></p>
</div>

<div class="edp-mb-section-title"><?php esc_html_e( 'Other cities section', 'emergencydentalpros' ); ?></div>

<div class="edp-toggle-row">
    <label class="edp-toggle">
        <input type="checkbox" name="edp_show_other_cities" id="edp_show_other_cities" value="1"
            <?php checked( $show_other_cities, 1 ); ?> />
        <span class="edp-toggle-slider"></span>
    </label>
    <span class="edp-toggle-label"><?php esc_html_e( 'Show "Other Cities" section on this page', 'emergencydentalpros' ); ?></span>
</div>

<script>
(function () {
    var RANGES = {
        edp_meta_title:       { min: 50, max: 60 },
        edp_meta_description: { min: 130, max: 160 },
    };

    function updateCounter(input) {
        var counter = document.querySelector('.edp-mb-counter[data-for="' + input.id + '"]');
        if (!counter) return;
        var len = input.value.length;
        var r = RANGES[input.id];
        counter.textContent = len + ' chars';
        counter.className = 'edp-mb-counter';
        if (!r) { counter.classList.add('edp-mb-counter--warn'); return; }
        if (len === 0)                         counter.classList.add('edp-mb-counter--warn');
        else if (len >= r.min && len <= r.max) counter.classList.add('edp-mb-counter--ok');
        else if (len > r.max)                  counter.classList.add('edp-mb-counter--err');
        else                                   counter.classList.add('edp-mb-counter--warn');
    }

    ['edp_meta_title', 'edp_meta_description', 'edp_h1', 'edp_communities_h2'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        updateCounter(el);
        el.addEventListener('input', function () { updateCounter(el); });
    });
}());
</script>
