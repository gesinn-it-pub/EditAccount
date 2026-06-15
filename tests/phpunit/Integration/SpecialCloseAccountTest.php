<?php

declare( strict_types=1 );

use MediaWiki\Extension\EditAccount\SpecialCloseAccount;
use MediaWiki\Request\FauxRequest;

/**
 * @group Integration
 * @group Database
 * @covers \MediaWiki\Extension\EditAccount\SpecialCloseAccount
 */
class SpecialCloseAccountTest extends SpecialPageTestBase {

	protected function newSpecialPage(): SpecialCloseAccount {
		$services = $this->getServiceContainer();
		return new SpecialCloseAccount(
			$services->getUserGroupManager(),
			$services->getUserNameUtils(),
			$services->getUserOptionsManager(),
			$services->getPasswordFactory(),
			$services->getUserFactory(),
			$services->getDBLoadBalancer()
		);
	}

	public function testAnonymousUserThrowsPermissionsError(): void {
		$this->expectException( \PermissionsError::class );
		$this->executeSpecialPage( '', new FauxRequest() );
	}

	public function testGetGroupNameReturnsUsers(): void {
		$this->assertSame( 'users', $this->newSpecialPage()->getGroupName() );
	}

	public function testGetDescriptionReturnsGeneralDescription(): void {
		$page = $this->newSpecialPage();

		$context = new \DerivativeContext( \RequestContext::getMain() );
		$context->setRequest( new FauxRequest() );
		$context->setUser( $this->getMutableTestUser()->getUser() );
		$page->setContext( $context );

		// getDescription() is called before execute() — test directly
		$description = $page->getDescription();
		$this->assertStringContainsString( 'Close', $description );
	}

	public function testStaffMemberIsRedirectedToEditAccount(): void {
		$staff = $this->getTestUser( [ 'staff' ] )->getAuthority();
		$page = $this->newSpecialPage();

		$context = new \DerivativeContext( \RequestContext::getMain() );
		$context->setRequest( new FauxRequest() );
		$context->setAuthority( $staff );
		$page->setContext( $context );

		$page->execute( null );

		$redirect = $context->getOutput()->getRedirect();
		$this->assertNotEmpty( $redirect );
		$this->assertStringContainsString( 'EditAccount', $redirect );
	}

	public function testIsListedFalseForStaff(): void {
		$staff = $this->getTestUser( [ 'staff' ] )->getUser();
		$page = $this->newSpecialPage();

		$context = new \DerivativeContext( \RequestContext::getMain() );
		$context->setRequest( new FauxRequest() );
		$context->setUser( $staff );
		$page->setContext( $context );

		$this->assertFalse( $page->isListed() );
	}

	public function testIsListedTrueForRegularUser(): void {
		$user = $this->getMutableTestUser()->getUser();
		$page = $this->newSpecialPage();

		$context = new \DerivativeContext( \RequestContext::getMain() );
		$context->setRequest( new FauxRequest() );
		$context->setUser( $user );
		$page->setContext( $context );

		$this->assertTrue( $page->isListed() );
	}

	public function testIsListedFalseForAnonymous(): void {
		$page = $this->newSpecialPage();

		$context = new \DerivativeContext( \RequestContext::getMain() );
		$context->setRequest( new FauxRequest() );
		$context->setUser( \User::newFromId( 0 ) );
		$page->setContext( $context );

		$this->assertFalse( $page->isListed() );
	}

	public function testGetRequestShowsCloseAccountForm(): void {
		$user = $this->getMutableTestUser()->getAuthority();
		[ $html ] = $this->executeSpecialPage( '', new FauxRequest(), null, $user );
		$this->assertStringContainsString( 'editaccountSelectForm', $html );
	}

	public function testPostRequestClosesAccountAndShowsSuccess(): void {
		$user = $this->getMutableTestUser()->getAuthority();
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [ 'wpReason' => '' ], true ),
			null,
			$user
		);
		$this->assertStringContainsString( 'editaccount-success-close', $html );
	}

}
