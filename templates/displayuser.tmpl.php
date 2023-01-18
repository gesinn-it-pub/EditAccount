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

		if ( $status !== null ) {
			if ( $status ) {
				echo Xml::element( 'div', [ 'class' => 'successbox' ], $statusMsg );
			} else {
				echo Xml::element( 'div', [ 'class' => 'errorbox' ], $statusMsg );
			}
			if ( !empty( $statusMsg2 ) ) {
				echo Xml::element( 'div', [ 'class' => 'errorbox' ], $statusMsg2 );
			}
		}
?>
<!-- s:<?php echo __FILE__ ?> -->
<div class="edit-account-card" id="edit-account-edit-card">
	<div class="edit-account-card-header"><?php echo wfMessage( 'editaccount-frame-account', $user )->escaped() ?></div>
    <div class="edit-account-card-body">
        <div class="edit-account-user-info">
            <div class="edit-account-user-info-row"><?php echo wfMessage( 'editaccount-user-id', $userId )->parse(); ?></div>
            <div class="edit-account-user-info-row"><?php echo wfMessage( 'editaccount-user-reg-date', $userReg )->parse(); ?></div>
            <div class="edit-account-user-info-row"><?php echo wfMessage( 'editaccount-label-account-status', $userStatus )->parse(); ?></div>
            <div class="edit-account-user-info-row"><?php echo wfMessage( 'editaccount-label-email-status', $emailStatus )->parse(); ?></div>
            <div class="edit-account-user-info-row"><?php echo $changeEmailRequested; ?></div>
        </div>
        <form method="post" action="" id="edit-account-edit-form">
            <div class="edit-account-form-fields">
                <div class="edit-account-form-row">
                    <input type="radio" id="wpActionSetEmail" name="wpAction" value="setemail" class="edit-account-form-cell" />
                    <label for="wpActionSetEmail" class="edit-account-form-cell"><?php echo wfMessage( 'editaccount-label-email' )->escaped() ?></label>
                    <input type="text" name="wpNewEmail" value="<?php echo $userEmail ?>" class="edit-account-form-cell edit-account-form-input-text"/>
                </div>

                <div class="edit-account-form-row">
                    <input type="radio" id="wpActionSetPass" name="wpAction" value="setpass" class="edit-account-form-cell"/>
                    <label for="wpActionSetPass" class="edit-account-form-cell"><?php echo wfMessage( 'editaccount-label-pass' )->escaped() ?></label>
                    <input type="text" name="wpNewPass" class="edit-account-form-cell edit-account-form-input-text" />
                </div>

                <div class="edit-account-form-row">
                    <input type="radio" id="wpActionSetRealName" name="wpAction" value="setrealname" class="edit-account-form-cell" <?php echo $disabled; ?> />
                    <label for="wpActionSetRealName" class="edit-account-form-cell"><?php echo wfMessage( 'editaccount-label-realname' )->escaped() ?></label>
                    <input type="text" name="wpNewRealName" value="<?php echo $userRealName ?>" <?php echo $disabled; ?> class="edit-account-form-cell edit-account-form-input-text" />
                </div>

                <?php if ( class_exists( 'AutoWikiAdoption' ) ) {
                /* We don't use that extension, so I'm hiding this option from the GUI --JP 20 October 2012 */ ?>
                    <div class="edit-account-form-row">
                    <input type="radio" id="wpActionToggleAdopt" name="wpAction" value="toggleadopter" class="edit-account-form-cell" />
                    <label for="wpActionToggleAdopt" class="edit-account-form-cell"><?php echo wfMessage( 'editaccount-label-toggleadopt' )->escaped() ?></label>
                    <span class="edit-account-form-cell"><?php echo ( $isAdopter ) ? wfMessage( 'editaccount-label-toggleadopt-prevent' )->escaped() : wfMessage( 'editaccount-label-toggleadopt-allow' )->escaped() ?></span>
                </div>
                <?php } ?>

                <?php if ( $isUnsub ) { ?>
                <div class="edit-account-form-row">
                    <input type="radio" id="wpActionClearUnsub" name="wpAction" value="clearunsub" <?php echo $disabled; ?> class="edit-account-form-cell" />
                    <label for="wpActionClearUnsub" class="edit-account-form-cell"><?php echo wfMessage( 'editaccount-submit-clearunsub' )->escaped() ?></label>
                </div>
                <?php }
                // end unsub ?>

                <div class="edit-account-form-row">
                    <div class="edit-account-form-cell"></div>
                    <label for="wpReason" class="edit-account-form-cell"><?php echo wfMessage( 'editaccount-label-reason' )->escaped() ?></label>
                    <input id="wpReason" name="wpReason" type="text" class="edit-account-form-cell edit-account-form-input-text" />
                </div>
            </div>
            <div>
                <input type="submit" value="<?php echo wfMessage( 'editaccount-submit-button' )->escaped() ?>" />
            </div>

            <input type="hidden" name="wpUserName" value="<?php echo $user_hsc ?>" />
        </form>
    </div>
</div>
<div class="edit-account-card" id="edit-account-close-card">
	<div class="edit-account-card-header"><?php echo wfMessage( 'editaccount-frame-close', $user )->escaped() ?></div>
    <div class="edit-account-card-body">
	<p><?php echo wfMessage( 'editaccount-usage-close' )->plain() ?></p>
	<form method="post" action="" id="edit-account-close-form">
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
    </div>
</div>
<!-- e:<?php echo __FILE__ ?> -->
<?php
	}
}
