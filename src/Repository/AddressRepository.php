<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Address;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

/**
 * @extends ServiceEntityRepository<Address>
 */
class AddressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Address::class);
    }

    public function save(Address $address, bool $flush = false): void
    {
        $this->getEntityManager()->persist($address);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Address $address, bool $flush = false): void
    {
        $this->getEntityManager()->remove($address);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.isDefault', 'DESC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findDefaultByUser(User $user): ?Address
    {
        return $this->findOneBy(['user' => $user, 'isDefault' => true]);
    }

    /** @throws Exception */
    public function setDefaultAddress(Address $address): void
    {
        // Démarrer une transaction
        $em = $this->getEntityManager();
        $em->beginTransaction();

        try {
            // Retirer le statut par défaut des autres adresses de l'utilisateur
            $this->createQueryBuilder('a')
                ->update()
                ->set('a.isDefault', ':false')
                ->where('a.user = :user')
                ->andWhere('a.id != :addressId')
                ->setParameter('false', false)
                ->setParameter('user', $address->getUser())
                ->setParameter('addressId', $address->getId())
                ->getQuery()
                ->execute();

            // Définir cette adresse comme par défaut
            $address->setDefault(true);
            $this->save($address);

            $em->commit();
        } catch (Exception $e) {
            $em->rollback();

            throw $e;
        }
    }

    public function findByCity(string $city): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.city LIKE :city')
            ->setParameter('city', '%' . $city . '%')
            ->orderBy('a.city', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCountry(string $country): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.country = :country')
            ->setParameter('country', $country)
            ->orderBy('a.city', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByZipCode(string $zipCode): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.zipCode = :zipCode')
            ->setParameter('zipCode', $zipCode)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function searchAddresses(string $search): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.street LIKE :search')
            ->orWhere('a.city LIKE :search')
            ->orWhere('a.zipCode LIKE :search')
            ->orWhere('a.country LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('a.city', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getAddressStatistics(): array
    {
        return [
            'totalAddresses' => $this->countAll(),
            'addressesByCountry' => $this->getAddressesByCountry(),
            'mostPopularCities' => $this->getMostPopularCities(10),
        ];
    }

    private function countAll(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getAddressesByCountry(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.country', 'COUNT(a.id) as count')
            ->groupBy('a.country')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    private function getMostPopularCities(int $limit): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.city', 'a.country', 'COUNT(a.id) as count')
            ->groupBy('a.city', 'a.country')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
