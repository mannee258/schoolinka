<?php
	global $wpdb, $pmpro_invoice, $pmpro_msg, $pmpro_msgt, $current_user;

	if($pmpro_msg)
	{
	?>
	<div class="pmpro_message <?php echo vibe_sanitizer($pmpro_msgt)?>"><?php echo vibe_sanitizer($pmpro_msg)?></div>
	<?php
	}
?>

<?php
	if($pmpro_invoice)
	{
		?>
		<?php
			$pmpro_invoice->getUser();
			$pmpro_invoice->getMembershipLevel();
		?>

		<h3>
			<?php printf(__('Invoice #%s on %s', 'vibe' ), $pmpro_invoice->code, date_i18n(get_option('date_format'), $pmpro_invoice->timestamp));?>
		</h3>
		<a class="pmpro_a-print" href="javascript:window.print()"><?php _e('Print', 'vibe' ); ?></a>
		<ul>
			<?php do_action("pmpro_invoice_bullets_top", $pmpro_invoice); ?>
			<li><strong><?php _e('Account', 'vibe' );?>:</strong> <?php echo vibe_sanitizer($pmpro_invoice->user->display_name)?> (<?php echo vibe_sanitizer($pmpro_invoice->user->user_email)?>)</li>
			<li><strong><?php _e('Membership Level', 'vibe' );?>:</strong> <?php echo vibe_sanitizer($pmpro_invoice->membership_level->name)?></li>
			<li><strong><?php _e('Status', 'vibe' ); ?>:</strong> <?php echo ! empty( $pmpro_invoice->status ) ? $pmpro_invoice->status : __( 'success', 'vibe' ); ?></li>
			<?php if($current_user->membership_level->enddate) { ?>
				<li><strong><?php _e('Membership Expires', 'vibe' );?>:</strong> <?php echo date_i18n(get_option('date_format'), $current_user->membership_level->enddate)?></li>
			<?php } ?>
			<?php if($pmpro_invoice->getDiscountCode()) { ?>
				<li><strong><?php _e('Discount Code', 'vibe' );?>:</strong> <?php echo vibe_sanitizer($pmpro_invoice->discount_code->code)?></li>
			<?php } ?>
			<?php do_action("pmpro_invoice_bullets_bottom", $pmpro_invoice); ?>
		</ul>

		<?php
			//check instructions
			if($pmpro_invoice->gateway == "check" && !pmpro_isLevelFree($pmpro_invoice->membership_level))
				echo wpautop(pmpro_getOption("instructions"));
		?>

		<hr />
		<div class="pmpro_invoice_details">
			<?php if(!empty($pmpro_invoice->billing->name)) { ?>
				<div class="pmpro_invoice-billing-address">
					<strong><?php _e('Billing Address', 'vibe' );?></strong>
					<p><?php echo vibe_sanitizer($pmpro_invoice->billing->name)?><br />
					<?php echo vibe_sanitizer($pmpro_invoice->billing->street)?><br />
					<?php if($pmpro_invoice->billing->city && $pmpro_invoice->billing->state) { ?>
						<?php echo vibe_sanitizer($pmpro_invoice->billing->city)?>, <?php echo vibe_sanitizer($pmpro_invoice->billing->state)?> <?php echo vibe_sanitizer($pmpro_invoice->billing->zip)?> <?php echo vibe_sanitizer($pmpro_invoice->billing->country)?><br />
					<?php } ?>
					<?php echo formatPhone($pmpro_invoice->billing->phone)?>
					</p>
				</div> <!-- end pmpro_invoice-billing-address -->
			<?php } ?>

			<?php if($pmpro_invoice->accountnumber) { ?>
				<div class="pmpro_invoice-payment-method">
					<strong><?php _e('Payment Method', 'vibe' );?></strong>
					<p><?php echo vibe_sanitizer($pmpro_invoice->cardtype)?> <?php _e('ending in', 'vibe' );?> <?php echo last4($pmpro_invoice->accountnumber)?></p>
					<p><?php _e('Expiration', 'vibe' );?>: <?php echo vibe_sanitizer($pmpro_invoice->expirationmonth)?>/<?php echo vibe_sanitizer($pmpro_invoice->expirationyear)?></p>
				</div> <!-- end pmpro_invoice-payment-method -->
			<?php } elseif($pmpro_invoice->payment_type) { ?>
				<?php echo vibe_sanitizer($pmpro_invoice->payment_type)?>
			<?php } ?>

			<div class="pmpro_invoice-total">
				<strong><?php _e('Total Billed', 'vibe' );?></strong>
				<p><?php if($pmpro_invoice->total != '0.00') { ?>
					<?php if(!empty($pmpro_invoice->tax)) { ?>
						<?php _e('Subtotal', 'vibe' );?>: <?php echo pmpro_formatPrice($pmpro_invoice->subtotal);?><br />
						<?php _e('Tax', 'vibe' );?>: <?php echo pmpro_formatPrice($pmpro_invoice->tax);?><br />
						<?php if(!empty($pmpro_invoice->couponamount)) { ?>
							<?php _e('Coupon', 'vibe' );?>: (<?php echo pmpro_formatPrice($pmpro_invoice->couponamount);?>)<br />
						<?php } ?>
						<strong><?php _e('Total', 'vibe' );?>: <?php echo pmpro_formatPrice($pmpro_invoice->total);?></strong>
					<?php } else { ?>
						<?php echo pmpro_formatPrice($pmpro_invoice->total);?>
					<?php } ?>
				<?php } else { ?>
					<small class="pmpro_grey"><?php echo pmpro_formatPrice(0);?></small>
				<?php } ?></p>
			</div> <!-- end pmpro_invoice-total -->
		</div> <!-- end pmpro_invoice -->
		<hr />
		<?php
	}
	else
	{
		//Show all invoices for user if no invoice ID is passed
		$invoices = $wpdb->get_results("SELECT o.*, UNIX_TIMESTAMP(o.timestamp) as timestamp, l.name as membership_level_name FROM $wpdb->pmpro_membership_orders o LEFT JOIN $wpdb->pmpro_membership_levels l ON o.membership_id = l.id WHERE o.user_id = '$current_user->ID' AND o.status NOT IN('review', 'token', 'error') ORDER BY timestamp DESC");
		if($invoices)
		{
			?>
			<table id="pmpro_invoices_table" class="pmpro_invoice" width="100%" cellpadding="0" cellspacing="0" border="0">
			<thead>
				<tr>
					<th><?php _e('Date', 'vibe' ); ?></th>
					<th><?php _e('Invoice #', 'vibe' ); ?></th>
					<th><?php _e('Level', 'vibe' ); ?></th>
					<th><?php _e('Total Billed', 'vibe' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
				foreach($invoices as $invoice)
				{
					?>
					<tr>
						<td><a href="<?php echo pmpro_url("invoice", "?invoice=" . $invoice->code)?>"><?php echo date_i18n(get_option("date_format"), $invoice->timestamp)?></a></td>
						<td><a href="<?php echo pmpro_url("invoice", "?invoice=" . $invoice->code)?>"><?php echo vibe_sanitizer($invoice->code); ?></a></td>
						<td><?php echo vibe_sanitizer($invoice->membership_level_name);?></td>
						<td><?php echo pmpro_formatPrice($invoice->total);?></td>
					</tr>
					<?php
				}
			?>
			</tbody>
			</table>
			<?php
		}
		else
		{
			?>
			<p><?php _e('No invoices found.', 'vibe' );?></p>
			<?php
		}
	}
?>
<nav id="nav-below" class="navigation" role="navigation">
	<div class="nav-next alignright">
		<a href="<?php echo pmpro_url("account")?>"><?php _e('View Your Membership Account &rarr;', 'vibe' );?></a>
	</div>
	<?php if($pmpro_invoice) { ?>
		<div class="nav-prev alignleft">
			<a href="<?php echo pmpro_url("invoice")?>"><?php _e('&larr; View All Invoices', 'vibe' );?></a>
		</div>
	<?php } ?>
</nav>
