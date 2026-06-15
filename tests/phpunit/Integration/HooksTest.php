<?php

declare( strict_types=1 );

use MediaWiki\Extension\EditAccount\Hooks;
use MediaWiki\Extension\EditAccount\SpecialEditAccount;

/**
 * @group Integration
 * @group Database
 * @covers \MediaWiki\Extension\EditAccount\Hooks
 */
class HooksTest extends MediaWikiIntegrationTestCase {

	public function testOnSpecialContributionsBeforeMainOutputDoesNothingForActiveUser(): void {
		$user = $this->getMutableTestUser()->getUser();
		$out = $this->createMock( OutputPage::class );
		$out->expects( $this->never() )->method( 'wrapWikiMsg' );
		$out->expects( $this->never() )->method( 'addHTML' );
		$skin = $this->createMock( Skin::class );

		$result = Hooks::onSpecialContributionsBeforeMainOutput( $user->getId(), $user, $out, $skin );

		$this->assertTrue( $result );
	}

	public function testOnSpecialContributionsBeforeMainOutputAddsBoxForDisabledAccount(): void {
		$user = $this->getMutableTestUser()->getUser();

		// Mark the account disabled
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, 'disabled', 1 );
		$userOptionsManager->saveOptions( $user );

		$out = $this->createMock( OutputPage::class );
		$out->expects( $this->once() )
			->method( 'wrapWikiMsg' )
			->with( $this->stringContains( 'errorbox' ), 'edit-account-closed-flag' );
		$out->expects( $this->once() )->method( 'addHTML' );
		$skin = $this->createMock( Skin::class );

		$result = Hooks::onSpecialContributionsBeforeMainOutput( $user->getId(), $user, $out, $skin );

		$this->assertTrue( $result );
	}

	public function testIsAccountDisabledReturnsFalseForActiveUser(): void {
		$user = $this->getMutableTestUser()->getUser();
		$this->assertFalse( SpecialEditAccount::isAccountDisabled( $user ) );
	}

	public function testIsAccountDisabledReturnsTrueAfterDisabling(): void {
		$user = $this->getMutableTestUser()->getUser();

		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, 'disabled', 1 );
		$userOptionsManager->saveOptions( $user );

		$this->assertTrue( SpecialEditAccount::isAccountDisabled( $user ) );
	}

}
