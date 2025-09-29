<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\EventListener\UserRegisteredEvent;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function count;
use function in_array;

readonly class UserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function createUser(array $data): User
    {
        $this->validateUniqueEmail($data['email']);

        $user = new User();
        $this->populateUserData($user, $data);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Dispatcher l'événement de création d'utilisateur
        $this->eventDispatcher->dispatch(new UserRegisteredEvent($user));

        return $user;
    }

    public function updateUser(int $userId, array $data): User
    {
        $user = $this->getUser($userId);

        if (isset($data['email']) && $data['email'] !== $user->getEmail()) {
            $this->validateUniqueEmail($data['email']);
            $user->setEmail($data['email']);
        }

        $this->updateUserFields($user, $data);
        $this->handleAvatarUpload($user, $data);

        $user->setUpdatedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        return $user;
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): User
    {
        $user = $this->getUser($userId);

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new BadRequestHttpException('Mot de passe actuel incorrect');
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $user;
    }

    public function resetPassword(string $email, string $newPassword): User
    {
        $user = $this->getUserByEmail($email);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $user;
    }

    public function verifyUser(int $userId): User
    {
        $user = $this->getUser($userId);
        $user->setVerified(true);
        $user->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $user;
    }

    public function deactivateUser(int $userId): User
    {
        $user = $this->getUser($userId);
        // Ici on pourrait ajouter un champ isActive si nécessaire
        // Pour l'instant, on peut utiliser les rôles
        $user->setRoles(['ROLE_INACTIVE']);
        $user->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $user;
    }

    public function promoteToAdmin(int $userId): User
    {
        $user = $this->getUser($userId);
        $roles = $user->getRoles();

        if (!in_array('ROLE_ADMIN', $roles, true)) {
            $roles[] = 'ROLE_ADMIN';
            $user->setRoles($roles);
            $user->setUpdatedAt(new DateTimeImmutable());

            $this->entityManager->flush();
        }

        return $user;
    }

    public function getUser(int $userId): User
    {
        $user = $this->userRepository->find($userId);
        if (!$user) {
            throw new NotFoundHttpException('Utilisateur non trouvé');
        }

        return $user;
    }

    public function getUserByEmail(string $email): User
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            throw new NotFoundHttpException('Utilisateur non trouvé');
        }

        return $user;
    }

    public function searchUsers(string $search): array
    {
        return $this->userRepository->searchUsers($search);
    }

    public function getRecentUsers(int $limit = 10): array
    {
        return $this->userRepository->findRecentUsers($limit);
    }

    public function getUsersWithOrders(): array
    {
        return $this->userRepository->findUsersWithOrders();
    }

    public function getUserStats(): array
    {
        return [
            'totalUsers' => $this->userRepository->count([]),
            'verifiedUsers' => $this->userRepository->count(['isVerified' => true]),
            'adminUsers' => $this->userRepository->countUsersByRole('ROLE_ADMIN'),
            'usersWithOrders' => count($this->getUsersWithOrders()),
        ];
    }

    public function getUserProfile(int $userId): array
    {
        $user = $this->getUser($userId);

        return [
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'fullName' => $user->getFullName(),
                'roles' => $user->getRoles(),
                'isVerified' => $user->isVerified(),
                'createdAt' => $user->getCreatedAt(),
                'avatarName' => $user->getAvatarName(),
            ],
            'addresses' => $user->getAddresses()->toArray(),
            'orders' => $user->getOrders()->toArray(),
            'cart' => $user->getCart() ? [
                'id' => $user->getCart()->getId(),
                'totalItems' => $user->getCart()->getTotalItems(),
                'totalQuantity' => $user->getCart()->getTotalQuantity(),
            ] : null,
        ];
    }

    // Méthodes privées pour la logique interne
    private function validateUniqueEmail(string $email): void
    {
        $existingUser = $this->userRepository->findByEmail($email);
        if ($existingUser) {
            throw new BadRequestHttpException('Un compte existe déjà avec cet email');
        }
    }

    private function populateUserData(User $user, array $data): void
    {
        $user->setEmail($data['email'])
            ->setFirstName($data['firstName'])
            ->setLastName($data['lastName']);

        if (isset($data['roles'])) {
            $user->setRoles($data['roles']);
        }
    }

    private function updateUserFields(User $user, array $data): void
    {
        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }

        if (isset($data['roles'])) {
            $user->setRoles($data['roles']);
        }
    }

    private function handleAvatarUpload(User $user, array $data): void
    {
        if (isset($data['avatarFile']) && $data['avatarFile'] instanceof UploadedFile) {
            $user->setAvatarFile($data['avatarFile']);
        }
    }
}
