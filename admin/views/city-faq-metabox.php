<?php
/**
 * FAQ metabox for edp_seo_city CPT.
 *
 * @package EmergencyDentalPros
 *
 * @var WP_Post $post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$faq_enabled = (int) get_post_meta( $post->ID, '_edp_faq_enabled', true );
// Default on when meta not yet set (empty string).
if ( get_post_meta( $post->ID, '_edp_faq_enabled', true ) === '' ) {
	$faq_enabled = 1;
}

$faq_h2    = (string) get_post_meta( $post->ID, '_edp_faq_h2',    true );
$faq_intro = (string) get_post_meta( $post->ID, '_edp_faq_intro', true );

$faq_items_raw = (string) get_post_meta( $post->ID, '_edp_faq_items', true );
$faq_items     = [];
if ( $faq_items_raw !== '' ) {
	$decoded = json_decode( $faq_items_raw, true );
	if ( is_array( $decoded ) ) {
		$faq_items = $decoded;
	}
}

wp_nonce_field( 'edp_faq_metabox_' . $post->ID, 'edp_faq_metabox_nonce' );
?>
<div>
	<?php /* Enable / disable toggle */ ?>
	<div class="edp-toggle-row">
		<label class="edp-toggle">
			<input type="checkbox" name="edp_faq_enabled" id="edp_faq_enabled" value="1"
				<?php checked( $faq_enabled, 1 ); ?> />
			<span class="edp-toggle-slider"></span>
		</label>
		<span class="edp-toggle-label"><?php esc_html_e( 'Show FAQ section on this page', 'emergencydentalpros' ); ?></span>
	</div>

	<div class="edp-faq-body<?php echo $faq_enabled ? '' : ' is-hidden'; ?>" id="edp-faq-body">

		<p style="font-size:12.64px;color:#89868D;margin-bottom:14px;">
			<?php esc_html_e( 'Leave H2, description, or items blank to inherit from the global template.', 'emergencydentalpros' ); ?>
		</p>

		<div class="edp-mb-row">
			<label for="edp_faq_h2"><?php esc_html_e( 'H2 (override)', 'emergencydentalpros' ); ?></label>
			<input type="text" name="edp_faq_h2" id="edp_faq_h2" value="<?php echo esc_attr( $faq_h2 ); ?>" />
		</div>

		<div class="edp-mb-row">
			<label for="edp_faq_intro"><?php esc_html_e( 'Short description (override)', 'emergencydentalpros' ); ?></label>
			<input type="text" name="edp_faq_intro" id="edp_faq_intro" value="<?php echo esc_attr( $faq_intro ); ?>" />
		</div>

		<div class="edp-mb-row">
			<label><?php esc_html_e( 'FAQ Items (override)', 'emergencydentalpros' ); ?></label>
			<div class="edp-faq-items-list" id="edp-cpt-faq-list">
				<?php foreach ( $faq_items as $item ) :
					$fq = (string) ( $item['q'] ?? '' );
					$fa = (string) ( $item['a'] ?? '' );
				?>
				<div class="edp-faq-item">
					<div class="edp-faq-item-fields">
						<input type="text" class="edp-faq-q" placeholder="<?php esc_attr_e( 'Question', 'emergencydentalpros' ); ?>" value="<?php echo esc_attr( $fq ); ?>" />
						<textarea class="edp-faq-a" rows="2" placeholder="<?php esc_attr_e( 'Answer', 'emergencydentalpros' ); ?>"><?php echo esc_textarea( $fa ); ?></textarea>
					</div>
					<button type="button" class="edp-faq-delete-btn" title="<?php esc_attr_e( 'Delete', 'emergencydentalpros' ); ?>">&#x2715;</button>
				</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="edp-faq-add-btn" id="edp-cpt-faq-add">
				+ <?php esc_html_e( 'Add Item', 'emergencydentalpros' ); ?>
			</button>
			<input type="hidden" name="edp_faq_items" id="edp_cpt_faq_json"
				value="<?php echo esc_attr( wp_json_encode( $faq_items ) ); ?>" />
		</div>
	</div><!-- .edp-faq-body -->
</div>

<script>
(function () {
	var toggle  = document.getElementById('edp_faq_enabled');
	var body    = document.getElementById('edp-faq-body');
	var list    = document.getElementById('edp-cpt-faq-list');
	var addBtn  = document.getElementById('edp-cpt-faq-add');
	var jsonIn  = document.getElementById('edp_cpt_faq_json');
	var theForm = jsonIn ? jsonIn.closest('form') : null;

	if (toggle && body) {
		toggle.addEventListener('change', function () {
			body.classList.toggle('is-hidden', !this.checked);
		});
	}

	function escAttr(s) {
		return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
	}
	function escHtml(s) {
		return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
	}

	function makeItem(q, a) {
		var wrap = document.createElement('div');
		wrap.className = 'edp-faq-item';
		wrap.innerHTML =
			'<div class="edp-faq-item-fields">'
			+ '<input type="text" class="edp-faq-q" placeholder="Question" value="' + escAttr(q) + '" />'
			+ '<textarea class="edp-faq-a" rows="2" placeholder="Answer">' + escHtml(a) + '</textarea>'
			+ '</div>'
			+ '<button type="button" class="edp-faq-delete-btn" title="Delete">&#x2715;</button>';
		wrap.querySelector('.edp-faq-delete-btn').addEventListener('click', function () { wrap.remove(); });
		return wrap;
	}

	if (list) {
		list.querySelectorAll('.edp-faq-delete-btn').forEach(function (btn) {
			btn.addEventListener('click', function () { btn.closest('.edp-faq-item').remove(); });
		});
	}

	if (addBtn && list) {
		addBtn.addEventListener('click', function () {
			list.appendChild(makeItem('', ''));
			list.lastElementChild.querySelector('.edp-faq-q').focus();
		});
	}

	if (theForm && list && jsonIn) {
		theForm.addEventListener('submit', function () {
			var items = [];
			list.querySelectorAll('.edp-faq-item').forEach(function (row) {
				var q = (row.querySelector('.edp-faq-q').value || '').trim();
				var a = (row.querySelector('.edp-faq-a').value || '').trim();
				if (q !== '') { items.push({ q: q, a: a }); }
			});
			jsonIn.value = JSON.stringify(items);
		});
	}
})();
</script>
