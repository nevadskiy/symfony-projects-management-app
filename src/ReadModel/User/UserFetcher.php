<?php

declare(strict_types=1);

namespace App\ReadModel\User;

use App\ReadModel\User\Filter\Filter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use LogicException;
use UnexpectedValueException;

class UserFetcher
{
    private $connection;
    private $paginator;

    public function __construct(Connection $connection, PaginatorInterface $paginator)
    {
        $this->connection = $connection;
        $this->paginator = $paginator;
    }

    public function existsByResetPasswordToken($token): bool
    {
        return $this->connection->createQueryBuilder()
                ->select('COUNT (*)')
                ->from('user_users')
                ->where('reset_password_token = :token')
                ->setParameter(':token', $token)
                ->execute()
                ->fetchColumn(0) > 0;
    }

    public function findForAuth(string $email): ?AuthView
    {
        $statement = $this->connection->createQueryBuilder()
            ->select(
                'id',
                'email',
                'password_hash',
                'TRIM(CONCAT(name_first, \' \', name_last)) AS name',
                'role',
                'status'
            )
            ->from('user_users')
            ->where('email = :email')
            ->setParameter(':email', $email)
            ->execute();

        $statement->setFetchMode(FetchMode::CUSTOM_OBJECT, AuthView::class);

        return $statement->fetch() ?: null;
    }

    public function findForAuthBySocialNetwork(string $socialNetwork, string $identity): ?AuthView
    {
        $statement = $this->connection->createQueryBuilder()
            ->select([
                'u.id',
                'u.email',
                'u.password_hash',
                'TRIM(CONCAT(u.name_first, \' \', u.name_last)) AS name',
                'u.role',
                'u.status',
            ])
            ->from('user_users', 'u')
            ->innerJoin('u', 'user_user_networks', 'n', 'n.user_id = u.id')
            ->where('n.name = :name AND n.identity = :identity')
            ->setParameter(':name', $socialNetwork)
            ->setParameter(':identity', $identity)
            ->execute();

        $statement->setFetchMode(FetchMode::CUSTOM_OBJECT, AuthView::class);

        return $statement->fetch() ?: null;
    }

    public function findByEmail(string $email): ?ShortView
    {
        $statement = $this->connection->createQueryBuilder()
            ->select('id', 'email', 'role', 'status')
            ->from('user_users')
            ->where('email = :email')
            ->setParameter(':email', $email)
            ->execute();

        $statement->setFetchMode(FetchMode::CUSTOM_OBJECT, ShortView::class);

        return $statement->fetch() ?: null;
    }

    public function findDetails(string $id): ?DetailsView
    {
        $statement = $this->connection->createQueryBuilder()
            ->select(
                'id',
                'register_date',
                'email',
                'role',
                'status',
                'name_first first_name',
                'name_last last_name'
            )
            ->from('user_users')
            ->where('id = :id')
            ->setParameter(':id', $id)
            ->execute();

        $statement->setFetchMode(FetchMode::CUSTOM_OBJECT, DetailsView::class);

        /** @var DetailsView $view */
        $view = $statement->fetch();

        $statement = $this->connection->createQueryBuilder()
            ->select('name', 'identity')
            ->from('user_social_networks')
            ->where('user_id = :id')
            ->setParameter(':id', $id)
            ->execute();

        $statement->setFetchMode(FetchMode::CUSTOM_OBJECT, SocialNetworkView::class);

        $view->socialNetworks = $statement->fetchAll();

        return $view;
    }

    public function getDetails(string $id): DetailsView
    {
        $details = $this->findDetails($id);

        if (!$details) {
            throw new LogicException('User is not found');
        }

        return $details;
    }

    public function findBySignUpConfirmToken(string $token): ?ShortView
    {
        $statement = $this->connection->createQueryBuilder()
            ->select(
                'id',
                'email',
                'role',
                'status'
            )
            ->from('user_users')
            ->where('confirm_token = :token')
            ->setParameter(':token', $token)
            ->execute();

        $statement->setFetchMode(FetchMode::CUSTOM_OBJECT, ShortView::class);

        return $statement->fetch() ?: null;
    }

    public function all(Filter $filter, int $page, int $size, string $sort, string $direction): PaginationInterface
    {
        $query = $this->connection->createQueryBuilder()
            ->select(
                'id',
                'register_date',
                'TRIM(CONCAT(name_first, \' \', name_last)) AS name',
                'email',
                'role',
                'status'
            )
            ->from('user_users');

        if ($filter->name) {
            $query->andWhere($query->expr()->like('LOWER(CONCAT(name_first, \' \', name_last))', ':name'));
            $query->setParameter(':name', '%' . mb_strtolower($filter->name) . '%');
        }

        if ($filter->email) {
            $query->andWhere($query->expr()->like('LOWER(email)', ':email'));
            $query->setParameter(':email', '%' . mb_strtolower($filter->email) . '%');
        }

        if ($filter->status) {
            $query->andWhere('status = :status');
            $query->setParameter(':status', $filter->status);
        }

        if ($filter->role) {
            $query->andWhere('role = :role');
            $query->setParameter(':role', $filter->role);
        }

        if (!\in_array($sort, ['register_date', 'name', 'email', 'role', 'status'], true)) {
            throw new UnexpectedValueException("Cannot sort by {$sort}");
        }

        $query->orderBy($sort, $direction === 'desc' ? 'desc' : 'asc');

        return $this->paginator->paginate($query, $page, $size);
    }
}
