<?php
/**
 * Payment box shown after checkout.
 *
 * Variables from Frontend::render_payment_box():
 *
 * @var array<string, mixed> $data           Payment data.
 * @var bool                 $show_simulator Whether to show the test button.
 *
 * @package VietQRCheckout
 */

defined( 'ABSPATH' ) || exit;

$vqrc_rows = array(
	array( __( 'Bank', 'vietqr-checkout' ), $data['bank_name'], false ),
	array( __( 'Account number', 'vietqr-checkout' ), $data['account'], true ),
	array( __( 'Account holder', 'vietqr-checkout' ), $data['name'], false ),
	array( __( 'Amount', 'vietqr-checkout' ), number_format( (float) $data['amount'], 0, ',', '.' ) . ' ₫', (string) $data['amount'] ),
	array( __( 'Transfer note', 'vietqr-checkout' ), $data['code'], true ),
);
?>
<section class="vqrc-box" data-state="waiting" aria-live="polite">
	<header class="vqrc-box__header">
		<h2 class="vqrc-box__title"><?php esc_html_e( 'Scan to pay', 'vietqr-checkout' ); ?></h2>
		<p class="vqrc-box__status">
			<span class="vqrc-dot" aria-hidden="true"></span>
			<span class="vqrc-box__status-text"><?php esc_html_e( 'Waiting for your transfer…', 'vietqr-checkout' ); ?></span>
			<?php if ( $data['expires_at'] ) : ?>
				<span class="vqrc-box__timer" data-expires="<?php echo esc_attr( $data['expires_at'] ); ?>"></span>
			<?php endif; ?>
		</p>
	</header>

	<div class="vqrc-box__body">
		<figure class="vqrc-box__qr">
			<img src="<?php echo esc_url( $data['qr_url'] ); ?>" alt="<?php esc_attr_e( 'VietQR payment code', 'vietqr-checkout' ); ?>" width="260" height="260" loading="eager">
			<figcaption><?php esc_html_e( 'Open your banking app and scan. Amount and note are filled in for you.', 'vietqr-checkout' ); ?></figcaption>
		</figure>

		<dl class="vqrc-box__details">
			<?php foreach ( $vqrc_rows as $vqrc_row ) : ?>
				<div class="vqrc-row">
					<dt><?php echo esc_html( $vqrc_row[0] ); ?></dt>
					<dd>
						<strong><?php echo esc_html( $vqrc_row[1] ); ?></strong>
						<?php if ( false !== $vqrc_row[2] ) : ?>
							<button type="button" class="vqrc-copy" data-copy="<?php echo esc_attr( true === $vqrc_row[2] ? $vqrc_row[1] : $vqrc_row[2] ); ?>">
								<?php esc_html_e( 'Copy', 'vietqr-checkout' ); ?>
							</button>
						<?php endif; ?>
					</dd>
				</div>
			<?php endforeach; ?>
		</dl>
	</div>

	<p class="vqrc-box__warning">
				<?php
				printf(
					/* translators: %s: payment code. */
					esc_html__( 'Keep the note %s exactly, otherwise the order cannot be matched automatically.', 'vietqr-checkout' ),
					'<code>' . esc_html( $data['code'] ) . '</code>'
				);
				?>
	</p>

	<?php if ( $show_simulator ) : ?>
		<footer class="vqrc-box__test">
			<span><?php esc_html_e( 'Test mode', 'vietqr-checkout' ); ?></span>
			<button type="button" class="vqrc-simulate"><?php esc_html_e( 'Simulate payment', 'vietqr-checkout' ); ?></button>
		</footer>
	<?php endif; ?>
</section>
