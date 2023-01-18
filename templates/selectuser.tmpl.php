<?php
/**
 * @file
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is not a valid entry point to MediaWiki.' );
}

/**
 * HTML template for Special:EditAccount -- main screen (for selecting what
 * user to edit)
 *
 * @ingroup Templates
 */
class EditAccountSelectUserTemplate extends QuickTemplate {
	public function execute() {
		$status = $this->data['status'];
		$statusMsg = $this->data['statusMsg'];
		$statusMsg2 = $this->data['statusMsg2'];
		$user_hsc = $this->data['user_hsc'];

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
		<form method="post" id="edit-account-select-form" action="">
			<fieldset>
				<input type="text" name="wpUserName" value="<?php echo $user_hsc; ?>" />
				<input type="submit" value="<?php echo wfMessage( 'editaccount-submit-account' )->plain(); ?>" />
				<input type="hidden" name="wpAction" value="displayuser" />
			</fieldset>
		</form>
<!-- e:<?php echo __FILE__ ?> -->
<?php
	}
}
