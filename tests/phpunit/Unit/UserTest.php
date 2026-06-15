<?php
/**
 * MediaWiki EditAccount Extension
 *
 * @link https://github.com/gesinn-it/EditAccount
 *
 * @author gesinn.it GmbH & Co. KG
 * @license MIT
 */

use MediaWiki\Extension\EditAccount\User;
use PHPUnit\Framework\TestCase;

/**
 * @group Unit
 * @covers \MediaWiki\Extension\EditAccount\User
 */
class UserTest extends TestCase {

    public function testGenerateTokenReturns32CharHexString() {
        $token = User::generateToken();
        $this->assertSame( 32, strlen( $token ) );
        $this->assertTrue( (bool)preg_match( '/^[0-9a-f]{32}$/', $token ) );
    }

    public function testGenerateTokenIsUnique() {
        $this->assertNotSame( User::generateToken(), User::generateToken() );
    }
}
