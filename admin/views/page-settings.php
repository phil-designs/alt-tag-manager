<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'search-alt-tags' ) );
}
$api_key = get_option( 'sat_anthropic_api_key', '' );
?>
<div class="wrap sat-wrap">
	<h1><?php esc_html_e( 'Alt Tag Settings', 'search-alt-tags' ); ?></h1>

	<div id="sat-settings-notice" class="notice" style="display:none;"></div>

	<form id="sat-settings-form">
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="sat-api-key"><?php esc_html_e( 'Anthropic API Key', 'search-alt-tags' ); ?></label>
					</th>
					<td>
						<div class="sat-api-key-row">
							<input type="password" id="sat-api-key" name="api_key"
								class="regular-text" value="<?php echo esc_attr( $api_key ); ?>"
								autocomplete="off" placeholder="sk-ant-…">
							<button type="button" id="sat-toggle-key" class="button"
								aria-label="<?php esc_attr_e( 'Show / hide key', 'search-alt-tags' ); ?>">
								<span class="dashicons dashicons-visibility"></span>
							</button>
						</div>
						<p class="description">
							<?php printf(
								/* translators: %s: link */
								esc_html__( 'Get your key from %s. Keys start with sk-ant-.', 'search-alt-tags' ),
								'<a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com</a>'
							); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Model', 'search-alt-tags' ); ?></th>
					<td>
						<code>claude-haiku-4-5-20251001</code>
						<p class="description"><?php esc_html_e( 'Claude Haiku 4.5 analyses each image with its vision capability and returns a descriptive alt tag. Haiku is optimised for speed and low cost — ideal for bulk processing.', 'search-alt-tags' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Image accessibility', 'search-alt-tags' ); ?></th>
					<td>
						<p class="description"><?php esc_html_e( 'AI generation works by sending the public URL of each image to the Anthropic API. Images must be publicly accessible (not behind a login or CDN auth) for this to work.', 'search-alt-tags' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" id="sat-save-settings" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'search-alt-tags' ); ?>
			</button>
			<span class="spinner" id="sat-settings-spinner"></span>
		</p>
	</form>

	<?php if ( ! empty( $api_key ) ) : ?>
	<hr>
	<h2><?php esc_html_e( 'API Connection Test', 'search-alt-tags' ); ?></h2>
	<p><?php esc_html_e( 'Send a minimal request to verify the key is valid.', 'search-alt-tags' ); ?></p>
	<button id="sat-test-btn" class="button"><?php esc_html_e( 'Test Connection', 'search-alt-tags' ); ?></button>
	<span id="sat-test-result" class="sat-test-result"></span>
	<?php endif; ?>
</div>

<script>
(function($){
	$('#sat-settings-form').on('submit', function(e){
		e.preventDefault();
		var $btn = $('#sat-save-settings'), $sp = $('#sat-settings-spinner');
		$btn.prop('disabled', true);
		$sp.addClass('is-active');
		$.post(SAT.ajaxUrl, {
			action: 'sat_save_settings', nonce: SAT.nonce,
			api_key: $('#sat-api-key').val()
		}, function(res){
			$btn.prop('disabled', false);
			$sp.removeClass('is-active');
			var $n = $('#sat-settings-notice');
			if (res.success) {
				$n.attr('class','notice notice-success').html('<p>'+res.data.message+'</p>').show();
			} else {
				$n.attr('class','notice notice-error').html('<p>'+res.data.message+'</p>').show();
			}
			setTimeout(function(){ $n.fadeOut(); }, 3000);
		});
	});

	$('#sat-toggle-key').on('click', function(){
		var $i = $('#sat-api-key');
		var hide = $i.attr('type') === 'password';
		$i.attr('type', hide ? 'text' : 'password');
		$(this).find('.dashicons').toggleClass('dashicons-visibility dashicons-hidden');
	});

	$('#sat-test-btn').on('click', function(){
		var $btn = $(this), $res = $('#sat-test-result');
		$btn.prop('disabled', true).text('<?php esc_html_e( 'Testing…', 'search-alt-tags' ); ?>');
		$res.text('').removeClass('sat-ok sat-fail');
		$.post(SAT.ajaxUrl, {
			action: 'sat_generate_alt_tag', nonce: SAT.nonce, attachment_id: 0
		}, function(res){
			$btn.prop('disabled', false).text('<?php esc_html_e( 'Test Connection', 'search-alt-tags' ); ?>');
			// Auth errors contain 401; an "Invalid attachment" error means the key works
			var msg = res.data ? (res.data.message || '') : '';
			if (!res.success && msg.indexOf('401') !== -1) {
				$res.addClass('sat-fail').text('✗ <?php esc_html_e( 'Invalid API key.', 'search-alt-tags' ); ?>');
			} else {
				$res.addClass('sat-ok').text('✓ <?php esc_html_e( 'API key is valid.', 'search-alt-tags' ); ?>');
			}
		});
	});
})(jQuery);
</script>
