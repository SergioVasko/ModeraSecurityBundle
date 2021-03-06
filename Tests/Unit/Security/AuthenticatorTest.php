<?php

namespace Modera\SecurityBundle\Tests\Unit\Security;

use Modera\SecurityBundle\Entity\User;
use Modera\SecurityBundle\Security\Authenticator;

/**
 * @author    Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2014 Modera Foundation
 */
class AuthenticatorTest extends \PHPUnit_Framework_TestCase
{
    private function createAuthenticator()
    {
        $om = \Phake::mock('Doctrine\Common\Persistence\ObjectManager');
        $user = \Phake::mock(User::clazz());
        $doctrine = \Phake::mock('Symfony\Bridge\Doctrine\RegistryInterface');
        $authenticatedTokenFactory = \Phake::mock('Modera\SecurityBundle\Security\AuthenticatedTokenFactory');

        \Phake::when($om)->persist($user)->thenReturn(null);
        \Phake::when($om)->flush()->thenReturn(null);
        \Phake::when($doctrine)->getManager()->thenReturn($om);

        return new Authenticator($doctrine, $authenticatedTokenFactory);
    }

    public function testCreateToken()
    {
        $authenticator = $this->createAuthenticator();

        $request = \Phake::mock('Symfony\Component\HttpFoundation\Request');

        $resp = $authenticator->createToken($request, 'username', 'password', 'test-key');
        $this->assertInstanceOf('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken', $resp);
    }

    public function testSupportsToken()
    {
        $authenticator = $this->createAuthenticator();

        $token = \Phake::mock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $resp = $authenticator->supportsToken($token, null);
        $this->assertFalse($resp);

        $token = \Phake::mock('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken');
        \Phake::when($token)->getProviderKey()->thenReturn('successful-test-key');

        $resp = $authenticator->supportsToken($token, 'failure-test-key');
        $this->assertFalse($resp);

        $resp = $authenticator->supportsToken($token, 'successful-test-key');
        $this->assertTrue($resp);
    }

    public function testResponseOnAuthenticationFailure()
    {
        $authenticator = $this->createAuthenticator();

        $request = \Phake::mock('Symfony\Component\HttpFoundation\Request');
        $exception = \Phake::mock('Symfony\Component\Security\Core\Exception\AuthenticationException');

        $resp = $authenticator->onAuthenticationFailure($request, $exception);
        $this->assertSame(null, $resp);

        \Phake::when($request)->isXmlHttpRequest()->thenReturn(true);

        $resp = $authenticator->onAuthenticationFailure($request, $exception);
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $resp);
    }

    public function testResponseOnAuthenticationSuccess()
    {
        $authenticator = $this->createAuthenticator();

        $request = \Phake::mock('Symfony\Component\HttpFoundation\Request');
        $token = \Phake::mock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $resp = $authenticator->onAuthenticationSuccess($request, $token);
        $this->assertSame(null, $resp);

        \Phake::when($request)->isXmlHttpRequest()->thenReturn(true);

        $resp = $authenticator->onAuthenticationSuccess($request, $token);
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $resp);
    }

    public function testUserStateChangeOnAuthenticationSuccess()
    {
        $user = new User;
        $authenticator = $this->createAuthenticator();

        $request = \Phake::mock('Symfony\Component\HttpFoundation\Request');
        $token = \Phake::mock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        \Phake::when($token)->getUser()->thenReturn($user);

        $this->assertSame(User::STATE_NEW, $user->getState());
        $authenticator->onAuthenticationSuccess($request, $token);
        $this->assertSame(User::STATE_ACTIVE, $user->getState());
    }

    public function testGetAuthenticationResponse()
    {
        $token = \Phake::mock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $resp = Authenticator::getAuthenticationResponse($token);
        $this->assertInternalType('array', $resp);
        $this->assertArrayHasKey('success', $resp);
        $this->assertFalse($resp['success']);

        $user = new User;
        \Phake::when($token)->isAuthenticated()->thenReturn(true);
        \Phake::when($token)->getUser()->thenReturn($user);
        \Phake::when($token)->getRoles()->thenReturn(array());

        $resp = Authenticator::getAuthenticationResponse($token);
        $this->assertInternalType('array', $resp);
        $this->assertArrayHasKey('success', $resp);
        $this->assertFalse($resp['success']);
        $this->assertArrayHasKey('message', $resp);

        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setEmail('john.doe@test.test');
        $user->setUsername('john.doe');
        $role = \Phake::mock('Symfony\Component\Security\Core\Role\RoleInterface');
        \Phake::when($role)->getRole()->thenReturn('ROLE_USER');
        \Phake::when($token)->getRoles()->thenReturn(array($role));

        $resp = Authenticator::getAuthenticationResponse($token);
        $this->assertInternalType('array', $resp);
        $this->assertArrayHasKey('success', $resp);
        $this->assertTrue($resp['success']);
        $this->assertArrayHasKey('profile', $resp);
        $this->assertInternalType('array', $resp['profile']);
        $this->assertEquals(array(
            'id'       => $user->getId(),
            'name'     => $user->getFullName(),
            'email'    => $user->getEmail(),
            'username' => $user->getUsername(),
        ), $resp['profile']);
    }
}
