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

// Field coverage count.
$all_overrides  = [ $val_meta_title, $val_meta_desc, $val_h1, $val_body, $val_comm_h2, $val_comm_body, $val_other_h2 ];
$filled_count   = count( array_filter( $all_overrides, fn( $v ) => $v !== '' ) );
$total_count    = count( $all_overrides );

wp_nonce_field( 'edp_location_settings_' . $post->ID, 'edp_location_settings_nonce' );
?>

<div class="edp-mb-city-title">
    <span class="dashicons dashicons-location"></span>
    <?php echo esc_html( $post->post_title ); ?>
    <span class="edp-mb-coverage-badge"><?php echo esc_html( "$filled_count / $total_count" ); ?> <?php esc_html_e( 'fields customized', 'emergencydentalpros' ); ?></span>
</div>

<?php if ( $row ) : ?>
<div class="edp-mb-location-bar">
    <?php if ( ! empty( $row['city'] ) ) : ?>
        <span><strong><?php echo esc_html( $row['city'] ); ?></strong></span>
    <?php endif; ?>
    <?php if ( ! empty( $row['state'] ) ) : ?>
        <span><?php echo esc_html( $row['state'] ); ?></span>
    <?php endif; ?>
    <?php if ( ! empty( $row['county'] ) ) : ?>
        <span><?php echo esc_html( $row['county'] ); ?></span>
    <?php endif; ?>
    <?php if ( ! empty( $row['zip'] ) ) : ?>
        <span>ZIP: <?php echo esc_html( $row['zip'] ); ?></span>
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

<div class="edp-mb-section-title">
    <?php esc_html_e( 'Main content', 'emergencydentalpros' ); ?>
    <span class="edp-mb-cqs-note"><?php esc_html_e( 'up to 25 CQS points', 'emergencydentalpros' ); ?></span>
</div>

<div class="edp-mb-row">
    <label><?php esc_html_e( 'Body text', 'emergencydentalpros' ); ?></label>
    <?php if ( $val_body === '' && $ph_body !== '' ) : ?>
        <p class="edp-mb-hint" style="margin-bottom:6px;"><?php esc_html_e( 'Showing template default — edit to override, clear to revert.', 'emergencydentalpros' ); ?></p>
    <?php endif; ?>
    <?php
    wp_editor(
        $val_body !== '' ? $val_body : $ph_body,
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
    <label><?php esc_html_e( 'Body text', 'emergencydentalpros' ); ?></label>
    <?php if ( $val_comm_body === '' && $ph_comm_body !== '' ) : ?>
        <p class="edp-mb-hint" style="margin-bottom:6px;"><?php esc_html_e( 'Showing template default — edit to override, clear to revert.', 'emergencydentalpros' ); ?></p>
    <?php endif; ?>
    <?php
    wp_editor(
        $val_comm_body !== '' ? $val_comm_body : $ph_comm_body,
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
    <label for="edp_other_cities_h2">
        <?php esc_html_e( 'H2', 'emergencydentalpros' ); ?>
        <span class="edp-mb-tip" data-tip="<?php esc_attr_e( 'Heading for the other cities section. Override to localise for this city.', 'emergencydentalpros' ); ?>">ⓘ</span>
    </label>
    <input type="text" name="edp_other_cities_h2" id="edp_other_cities_h2"
        value="<?php echo esc_attr( $val_other_h2 ); ?>"
        placeholder="<?php echo esc_attr( $ph_other_h2 ); ?>" />
    <span class="edp-mb-counter" data-for="edp_other_cities_h2"></span>
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

    ['edp_meta_title', 'edp_meta_description', 'edp_h1', 'edp_communities_h2', 'edp_other_cities_h2'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        updateCounter(el);
        el.addEventListener('input', function () { updateCounter(el); });
    });
}());
</script>
