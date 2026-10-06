<?php

declare( strict_types=1 );

use MediaWiki\Extension\EditAccount\SpecialEditAccount;

/**
 * @group Integration
 * @group Database
 * @covers \MediaWiki\Extension\EditAccount\Hooks
 */
class HooksTest extends MediaWikiIntegrationTestCase {

	private function runContributionsHook( User $user ): OutputPage {
		$context = new \DerivativeContext( RequestContext::getMain() );
		$out = new OutputPage( $context );
		$context->setOutput( $out );
		$context->setTitle( SpecialPage::getTitleFor( 'Contributions' ) );
		$context->setLanguage( 'qqx' );

		$sp = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'Contributions' );
		$sp->setContext( $context );

		$this->getServiceContainer()->getHookContainer()->run(
			'SpecialContributionsBeforeMainOutput',
			[ $user->getId(), $user, $sp ]
		);

		return $out;
	}

	public function testSpecialContributionsHookDoesNothingForActiveUser(): void {
		$user = $this->getMutableTestUser()->getUser();

		$out = $this->runContributionsHook( $user );

		$this->assertSame( '', $out->getHTML() );
	}

	public function testSpecialContributionsHookAddsBoxForDisabledAccount(): void {
		$user = $this->getMutableTestUser()->getUser();

		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, 'disabled', 1 );
		$userOptionsManager->saveOptions( $user );

		$out = $this->runContributionsHook( $user );

		$this->assertStringContainsString( 'account-disabled-box', $out->getHTML() );
		$this->assertStringContainsString( 'edit-account-closed-flag', $out->getHTML() );
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
