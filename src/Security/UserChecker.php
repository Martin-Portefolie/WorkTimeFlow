<?php

namespace App\Security;

use App\Entity\User as AppUser;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class UserChecker implements UserCheckerInterface
{
public function checkPreAuth(UserInterface $user): void
{
if (!$user instanceof AppUser) return;

if (!$user->isActive()) {
// message is shown to the user on login failure
throw new CustomUserMessageAccountStatusException('Account is deactivated.');
}
}

// If you’re on Symfony < 7.2 keep the signature with no TokenInterface:
public function checkPostAuth(UserInterface $user/*, TokenInterface $token for 7.2+ */): void {}
}
