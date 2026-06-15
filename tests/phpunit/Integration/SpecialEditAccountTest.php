<?php

declare( strict_types=1 );

use MediaWiki\Extension\EditAccount\SpecialEditAccount;
use MediaWiki\Request\FauxRequest;

/**
 * @group Integration
 * @group Database
 * @covers \MediaWiki\Extension\EditAccount\SpecialEditAccount
 */
class SpecialEditAccountTest extends SpecialPageTestBase {

	protected function newSpecialPage(): SpecialEditAccount {
		$services = $this->getServiceContainer();
		return new SpecialEditAccount(
			$services->getPasswordFactory(),
			$services->getUserNameUtils(),
			$services->getUserOptionsManager(),
			$services->getUserFactory(),
			$services->getUserIdentityLookup(),
			$services->getLinkRenderer(),
			$services->getDBLoadBalancer()
		);
	}

	private function getPrivilegedUser(): \MediaWiki\Permissions\Authority {
		return $this->getTestUser( [ 'staff' ] )->getAuthority();
	}

	public function testUnprivilegedUserIsRedirectedToCloseAccount(): void {
		$unprivileged = $this->getTestUser()->getAuthority();
		$page = $this->newSpecialPage();

		$context = new \DerivativeContext( \RequestContext::getMain() );
		$context->setRequest( new FauxRequest() );
		$context->setAuthority( $unprivileged );
		$page->setContext( $context );

		$page->execute( null );

		$redirect = $context->getOutput()->getRedirect();
		$this->assertNotEmpty( $redirect );
		$this->assertStringContainsString( 'CloseAccount', $redirect );
	}

	public function testPrivilegedUserSeesSelectUserFormWithNoSubpage(): void {
		[ $html ] = $this->executeSpecialPage( '', new FauxRequest(), null, $this->getPrivilegedUser() );
		$this->assertStringContainsString( 'edit-account-select-form', $html );
		$this->assertStringContainsString( 'wpUserName', $html );
	}

	public function testSelectUserFormIsShownForUnknownUser(): void {
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [ 'wpUserName' => 'NonExistentUser12345xyz' ] ),
			null,
			$this->getPrivilegedUser()
		);
		$this->assertStringContainsString( 'errorbox', $html );
		$this->assertStringContainsString( 'NonExistentUser12345xyz', $html );
	}

	public function testDisplayUserFormIsShownForExistingUser(): void {
		$testUser = $this->getMutableTestUser()->getUser();
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [ 'wpUserName' => $testUser->getName() ] ),
			null,
			$this->getPrivilegedUser()
		);
		$this->assertStringContainsString( 'edit-account-edit-form', $html );
		$this->assertStringContainsString( $testUser->getName(), $html );
	}

	public function testSubpageResolvesToDisplayUserForm(): void {
		$testUser = $this->getMutableTestUser()->getUser();
		[ $html ] = $this->executeSpecialPage(
			$testUser->getName(),
			new FauxRequest(),
			null,
			$this->getPrivilegedUser()
		);
		$this->assertStringContainsString( 'edit-account-edit-form', $html );
	}

	public function testSetEmailSuccessShowsSuccessBox(): void {
		$testUser = $this->getMutableTestUser()->getUser();
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [
				'wpUserName' => $testUser->getName(),
				'wpAction'   => 'setemail',
				'wpNewEmail' => 'newemail@example.com',
			], true ),
			null,
			$this->getPrivilegedUser()
		);
		$this->assertStringContainsString( 'successbox', $html );
	}

	public function testSetEmailWithInvalidAddressShowsErrorBox(): void {
		$testUser = $this->getMutableTestUser()->getUser();
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [
				'wpUserName' => $testUser->getName(),
				'wpAction'   => 'setemail',
				'wpNewEmail' => 'not-a-valid-email',
			], true ),
			null,
			$this->getPrivilegedUser()
		);
		$this->assertStringContainsString( 'errorbox', $html );
	}

	public function testSetEmailToBlankSucceeds(): void {
		$testUser = $this->getMutableTestUser()->getUser();
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [
				'wpUserName' => $testUser->getName(),
				'wpAction'   => 'setemail',
				'wpNewEmail' => '',
			], true ),
			null,
			$this->getPrivilegedUser()
		);
		$this->assertStringContainsString( 'successbox', $html );
	}

	public function testSetPasswordSuccessShowsSuccessBox(): void {
		$testUser = $this->getMutableTestUser()->getUser();
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [
				'wpUserName' => $testUser->getName(),
				'wpAction'   => 'setpass',
				'wpNewPass'  => 'NewPass#123!Aa',
			], true ),
			null,
			$this->getPrivilegedUser()
		);
		$this->assertStringContainsString( 'successbox', $html );
	}

	public function testSetRealNameSuccessShowsSuccessBox(): void {
		$testUser = $this->getMutableTestUser()->getUser();
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [
				'wpUserName'    => $testUser->getName(),
				'wpAction'      => 'setrealname',
				'wpNewRealName' => 'Test Real Name',
			], true ),
			null,
			$this->getPrivilegedUser()
		);
		$this->assertStringContainsString( 'successbox', $html );
	}

	public function testCloseAccountActionShowsCloseAccountTemplate(): void {
		$testUser = $this->getMutableTestUser()->getUser();
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [
				'wpUserName' => $testUser->getName(),
				'wpAction'   => 'closeaccount',
			], true ),
			null,
			$this->getPrivilegedUser()
		);
		$this->assertStringContainsString( 'editaccountSelectForm', $html );
	}

	public function testGetGroupNameReturnsUsers(): void {
		$page = $this->newSpecialPage();
		$this->assertSame( 'users', $page->getGroupName() );
	}

	public function testDoesWritesReturnsTrue(): void {
		$page = $this->newSpecialPage();
		$this->assertTrue( $page->doesWrites() );
	}

}
