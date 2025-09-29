<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\User\AddressRequest;
use App\DTO\User\UpdateProfileRequest;
use App\Entity\Address;
use App\Entity\User;
use App\Repository\AddressRepository;
use App\Service\UserService;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function count;

#[Route('/api/users', name: 'api_users_')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly AddressRepository $addressRepository,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/profile', name: 'profile_show', methods: ['GET'])]
    public function getProfile(#[CurrentUser] User $user): JsonResponse
    {
        $profile = $this->userService->getUserProfile($user->getId());

        return $this->json([
            'success' => true,
            'data' => $profile,
        ]);
    }

    #[Route('/profile', name: 'profile_update', methods: ['PUT', 'PATCH'])]
    public function updateProfile(
        #[MapRequestPayload]
        UpdateProfileRequest $request,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        try {
            $userData = [
                'firstName' => $request->firstName,
                'lastName' => $request->lastName,
                'email' => $request->email,
                'avatarFile' => $request->avatarFile,
            ];

            $updatedUser = $this->userService->updateUser($user->getId(), $userData);

            $this->logger->info('Profil utilisateur mis à jour', [
                'user_id' => $user->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'data' => [
                    'user' => [
                        'id' => $updatedUser->getId(),
                        'email' => $updatedUser->getEmail(),
                        'firstName' => $updatedUser->getFirstName(),
                        'lastName' => $updatedUser->getLastName(),
                        'fullName' => $updatedUser->getFullName(),
                        'avatarName' => $updatedUser->getAvatarName(),
                        'updatedAt' => $updatedUser->getUpdatedAt(),
                    ],
                ],
            ]);
        } catch (BadRequestHttpException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            $this->logger->error('Erreur mise à jour profil', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour',
            ], 500);
        }
    }

    #[Route('/addresses', name: 'addresses_list', methods: ['GET'])]
    public function getAddresses(#[CurrentUser] User $user): JsonResponse
    {
        $addresses = $this->addressRepository->findByUser($user);

        $addressesData = array_map(static function ($address) {
            return [
                'id' => $address->getId(),
                'firstName' => $address->getFirstName(),
                'lastName' => $address->getLastName(),
                'street' => $address->getStreet(),
                'city' => $address->getCity(),
                'zipCode' => $address->getZipCode(),
                'country' => $address->getCountry(),
                'phone' => $address->getPhone(),
                'isDefault' => $address->isDefault(),
                'formattedAddress' => $address->getFormattedAddress(),
                'createdAt' => $address->getCreatedAt(),
            ];
        }, $addresses);

        return $this->json([
            'success' => true,
            'data' => [
                'addresses' => $addressesData,
                'total' => count($addressesData),
            ],
        ]);
    }

    #[Route('/addresses', name: 'addresses_create', methods: ['POST'])]
    public function createAddress(
        #[MapRequestPayload]
        AddressRequest $request,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        try {
            $address = new Address();
            $address->setFirstName($request->firstName)
                ->setLastName($request->lastName)
                ->setStreet($request->street)
                ->setCity($request->city)
                ->setZipCode($request->zipCode)
                ->setCountry($request->country)
                ->setPhone($request->phone)
                ->setDefault($request->isDefault)
                ->setUser($user);

            // Si c'est l'adresse par défaut, gérer l'unicité
            if ($request->isDefault) {
                $this->addressRepository->setDefaultAddress($address);
            } else {
                $this->addressRepository->save($address, true);
            }

            $this->logger->info('Nouvelle adresse créée', [
                'user_id' => $user->getId(),
                'address_id' => $address->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Adresse créée avec succès',
                'data' => [
                    'address' => [
                        'id' => $address->getId(),
                        'formattedAddress' => $address->getFormattedAddress(),
                        'isDefault' => $address->isDefault(),
                        'createdAt' => $address->getCreatedAt(),
                    ],
                ],
            ], 201);
        } catch (Exception $e) {
            $this->logger->error('Erreur création adresse', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de l\'adresse',
            ], 500);
        }
    }

    #[Route('/addresses/{id}', name: 'addresses_show', methods: ['GET'])]
    public function getAddress(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $address = $this->addressRepository->find($id);

        if (!$address || $address->getUser() !== $user) {
            throw new NotFoundHttpException('Adresse non trouvée');
        }

        return $this->json([
            'success' => true,
            'data' => [
                'address' => [
                    'id' => $address->getId(),
                    'firstName' => $address->getFirstName(),
                    'lastName' => $address->getLastName(),
                    'street' => $address->getStreet(),
                    'city' => $address->getCity(),
                    'zipCode' => $address->getZipCode(),
                    'country' => $address->getCountry(),
                    'phone' => $address->getPhone(),
                    'isDefault' => $address->isDefault(),
                    'formattedAddress' => $address->getFormattedAddress(),
                    'createdAt' => $address->getCreatedAt(),
                ],
            ],
        ]);
    }

    #[Route('/addresses/{id}', name: 'addresses_update', methods: ['PUT', 'PATCH'])]
    public function updateAddress(
        int $id,
        #[MapRequestPayload]
        AddressRequest $request,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $address = $this->addressRepository->find($id);

        if (!$address || $address->getUser() !== $user) {
            throw new NotFoundHttpException('Adresse non trouvée');
        }

        try {
            $address->setFirstName($request->firstName)
                ->setLastName($request->lastName)
                ->setStreet($request->street)
                ->setCity($request->city)
                ->setZipCode($request->zipCode)
                ->setCountry($request->country)
                ->setPhone($request->phone)
                ->setDefault($request->isDefault)
                ->setUpdatedAt(new DateTimeImmutable());

            // Si c'est l'adresse par défaut, gérer l'unicité
            if ($request->isDefault) {
                $this->addressRepository->setDefaultAddress($address);
            } else {
                $this->addressRepository->save($address, true);
            }

            $this->logger->info('Adresse mise à jour', [
                'user_id' => $user->getId(),
                'address_id' => $address->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Adresse mise à jour avec succès',
                'data' => [
                    'address' => [
                        'id' => $address->getId(),
                        'formattedAddress' => $address->getFormattedAddress(),
                        'isDefault' => $address->isDefault(),
                        'updatedAt' => $address->getUpdatedAt(),
                    ],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur mise à jour adresse', [
                'user_id' => $user->getId(),
                'address_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour',
            ], 500);
        }
    }

    #[Route('/addresses/{id}', name: 'addresses_delete', methods: ['DELETE'])]
    public function deleteAddress(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $address = $this->addressRepository->find($id);

        if (!$address || $address->getUser() !== $user) {
            throw new NotFoundHttpException('Adresse non trouvée');
        }

        try {
            // Vérifier qu'il ne s'agit pas de la seule adresse
            $userAddresses = $this->addressRepository->findByUser($user);
            if (count($userAddresses) <= 1) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous devez conserver au moins une adresse',
                ], 400);
            }

            // Si on supprime l'adresse par défaut, en définir une autre
            if ($address->isDefault() && count($userAddresses) > 1) {
                $newDefaultAddress = null;
                foreach ($userAddresses as $addr) {
                    if ($addr->getId() !== $address->getId()) {
                        $newDefaultAddress = $addr;

                        break;
                    }
                }
                if ($newDefaultAddress) {
                    $this->addressRepository->setDefaultAddress($newDefaultAddress);
                }
            }

            $this->addressRepository->remove($address, true);

            $this->logger->info('Adresse supprimée', [
                'user_id' => $user->getId(),
                'address_id' => $id,
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Adresse supprimée avec succès',
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur suppression adresse', [
                'user_id' => $user->getId(),
                'address_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression',
            ], 500);
        }
    }

    #[Route('/addresses/{id}/set-default', name: 'addresses_set_default', methods: ['POST'])]
    public function setDefaultAddress(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $address = $this->addressRepository->find($id);

        if (!$address || $address->getUser() !== $user) {
            throw new NotFoundHttpException('Adresse non trouvée');
        }

        try {
            $this->addressRepository->setDefaultAddress($address);

            $this->logger->info('Adresse définie par défaut', [
                'user_id' => $user->getId(),
                'address_id' => $id,
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Adresse définie comme adresse par défaut',
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur définition adresse par défaut', [
                'user_id' => $user->getId(),
                'address_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function getUserStats(#[CurrentUser] User $user): JsonResponse
    {
        $orders = $user->getOrders();
        $totalSpent = 0;
        $completedOrders = 0;

        foreach ($orders as $order) {
            if ($order->getStatus() === 'delivered') {
                $totalSpent += (float) $order->getTotalPrice();
                ++$completedOrders;
            }
        }

        return $this->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'totalOrders' => $orders->count(),
                    'completedOrders' => $completedOrders,
                    'totalSpent' => number_format($totalSpent, 2),
                    'totalAddresses' => $user->getAddresses()->count(),
                    'memberSince' => $user->getCreatedAt(),
                    'isVerified' => $user->isVerified(),
                ],
            ],
        ]);
    }
}
