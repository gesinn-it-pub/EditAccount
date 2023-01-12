<?php
/**
 * @file
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is not a valid entry point to MediaWiki.' );
}

/**
 * HTML template for Special:EditAccount
 *
 * @ingroup Templates
 */
class EditAccountDisplayUserTemplate extends QuickTemplate {
	public function execute() {
		$returnURL = $this->data['returnURL'];
		$logLink = $this->data['logLink'];
		$status = $this->data['status'];
		$statusMsg = $this->data['statusMsg'];
		$statusMsg2 = $this->data['statusMsg2'];
		$user = $this->data['user'];
		$userEncoded = $this->data['userEncoded'];
		$userId = $this->data['userId'];
		$userReg = $this->data['userReg'];
		$userStatus = $this->data['userStatus'];
		$emailStatus = $this->data['emailStatus'];
		$changeEmailRequested = $this->data['changeEmailRequested'];
		$userEmail = $this->data['userEmail'];
		$user_hsc = $this->data['user_hsc'];
		$userRealName = $this->data['userRealName'];
		$isAdopter = $this->data['isAdopter'];
		$isUnsub = $this->data['isUnsub'];
		$disabled = $this->data['disabled'];
		$isDisabled = $this->data['isDisabled'];
?>
<!-- s:<?php echo __FILE__ ?> -->
<small><a href="<?php echo $returnURL; ?>"><?php echo wfMessage( 'editaccount-return' )->plain() ?></a><?php echo wfMessage( 'pipe-separator' )->plain() . $logLink ?></small>
<?php if ( $status !== null ) { ?>
<fieldset>
	<legend><?php echo wfMessage( 'editaccount-status' )->plain() ?></legend>
	<?php
		if ( $status ) {
			echo Xml::element( 'span', [ 'style' => 'color: darkgreen; font-weight: bold;' ], $statusMsg );
		} else {
			echo Xml::element( 'span', [ 'style' => 'color: #fe0000; font-weight: bold;' ], $statusMsg );
		}

		if ( !empty( $statusMsg2 ) ) {
			echo Xml::element( 'span', [ 'style' => 'color: #fe0000; font-weight: bold;' ], $statusMsg2 );
		}
	?>
</fieldset>
<?php } ?>
<fieldset>
	<legend><?php echo wfMessage( 'editaccount-frame-account', $user )->escaped() ?></legend>
    <div class="edit-account-user-info">
        <div class="edit-account-user-info-row"><?php echo wfMessage( 'editaccount-user-id', $userId )->parse(); ?></div>
        <div class="edit-account-user-info-row"><?php echo wfMessage( 'editaccount-user-reg-date', $userReg )->parse(); ?></div>
        <div class="edit-account-user-info-row"><?php echo wfMessage( 'editaccount-label-account-status', $userStatus )->parse(); ?></div>
        <div class="edit-account-user-info-row"><?php echo wfMessage( 'editaccount-label-email-status', $emailStatus )->parse(); ?></div>
        <div class="edit-account-user-info-row"><?php echo $changeEmailRequested; ?><br />
    </div>
	<form method="post" action="" id="EditAccountForm">
		<div>
			<input type="radio" id="wpActionSetEmail" name="wpAction" value="setemail" />
			<label for="wpActionSetEmail"><?php echo wfMessage( 'editaccount-label-email' )->escaped() ?></label>
			<input type="text" name="wpNewEmail" value="<?php echo $userEmail ?>" />
		</div>

		<div>
			<input type="radio" id="wpActionSetPass" name="wpAction" value="setpass" />
			<label for="wpActionSetPass"><?php echo wfMessage( 'editaccount-label-pass' )->escaped() ?></label>
			<input type="text" name="wpNewPass" />
		</div>

		<div>
			<input type="radio" id="wpActionSetRealName" name="wpAction" value="setrealname" <?php echo $disabled; ?> />
			<label for="wpActionSetRealName"><?php echo wfMessage( 'editaccount-label-realname' )->escaped() ?></label>
			<input type="text" name="wpNewRealName" value="<?php echo $userRealName ?>" <?php echo $disabled; ?> />
		</div>

		<?php if ( class_exists( 'AutoWikiAdoption' ) ) {
		/* We don't use that extension, so I'm hiding this option from the GUI --JP 20 October 2012 */ ?>
		<div>
			<input type="radio" id="wpActionToggleAdopt" name="wpAction" value="toggleadopter" />
			<label for="wpActionToggleAdopt"><?php echo wfMessage( 'editaccount-label-toggleadopt' )->escaped() ?></label>
			<span><?php echo ( $isAdopter ) ? wfMessage( 'editaccount-label-toggleadopt-prevent' )->escaped() : wfMessage( 'editaccount-label-toggleadopt-allow' )->escaped() ?></span>
		</div>
		<?php } ?>

		<?php if ( $isUnsub ) { ?>
		<div>
			<input type="radio" id="wpActionClearUnsub" name="wpAction" value="clearunsub" <?php echo $disabled; ?> />
			<label for="wpActionClearUnsub"><?php echo wfMessage( 'editaccount-submit-clearunsub' )->escaped() ?></label>
		</div>
		<?php }
		// end unsub ?>

		<div>
			<label for="wpReason"><?php echo wfMessage( 'editaccount-label-reason' )->escaped() ?></label>
			<input id="wpReason" name="wpReason" type="text" />
		</div>

		<div>
			<input type="submit" value="<?php echo wfMessage( 'editaccount-submit-button' )->escaped() ?>" />
		</div>

		<input type="hidden" name="wpUserName" value="<?php echo $user_hsc ?>" />
	</form>
</fieldset>
<fieldset>
	<legend><?php echo wfMessage( 'editaccount-frame-close', $user )->escaped() ?></legend>
	<p><?php echo wfMessage( 'editaccount-usage-close' )->plain() ?></p>
	<form method="post" action="">
		<input type="submit" value="<?php echo wfMessage( 'editaccount-submit-close' )->plain() ?>" <?php echo $disabled; ?> />
		<input type="hidden" name="wpAction" value="closeaccount" />
		<input type="hidden" name="wpUserName" value="<?php echo $user_hsc ?>" />
	</form>
<?php if ( $isDisabled ) {
	echo wfMessage( 'edit-account-closed-flag' )->plain(); ?>
	<form method="post" action="">
		<input type="submit" value="<?php echo wfMessage( 'editaccount-submit-cleardisable' )->plain() ?>" <?php echo $disabled; ?> />
		<input type="hidden" name="wpAction" value="cleardisable" />
		<input type="hidden" name="wpUserName" value="<?php echo $user_hsc ?>" />
	</form>
<?php }
// end undisable ?>
</fieldset>
<!-- e:<?php echo __FILE__ ?> -->
<?php
	}
}
