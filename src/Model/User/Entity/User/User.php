<?php

declare(strict_types=1);

namespace App\Model\User\Entity\User;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use DomainException;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Table(name="user_users", uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"email"}),
 *     @ORM\UniqueConstraint(columns={"reset_password_token"}),
 * })
 */
class User
{
    public const STATUS_WAIT = 'wait';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_BLOCKED = 'blocked';

    /**
     * @var Id
     * @ORM\Column(type="user_user_id")
     * @ORM\Id
     */
    private $id;
    /**
     * @var Email
     * @ORM\Column(type="user_user_email", nullable=true)
     */
    private $email;
    /**
     * @var string
     * @ORM\Column(type="string", nullable=true, name="password_hash")
     */
    private $passwordHash;
    /**
     * @var DateTimeImmutable
     * @ORM\Column(type="datetime_immutable", name="register_date")
     */
    private $registerDate;
    /**
     * @var string
     * @ORM\Column(type="string", nullable=true, name="confirm_token")
     */
    private $confirmToken;
    /**
     * @var Name
     * @ORM\Embedded(class="Name")
     */
    private $name;
    /**
     * @var Email|null
     * @ORM\Column(type="user_user_email", name="new_email", nullable=true)
     */
    private $newEmail;
    /**
     * @var string|null
     * @ORM\Column(type="string", name="new_email_token", nullable=true)
     */
    private $newEmailToken;
    /**
     * @var string
     * @ORM\Column(type="string", length=16)
     */
    private $status;
    /**
     * @var ResetPasswordToken|null
     * @ORM\Embedded(class="ResetPasswordToken", columnPrefix="reset_password_")
     */
    private $resetPasswordToken;
    /**
     * @var Role
     * @ORM\Column(type="user_user_role")
     */
    private $role;
    /**
     * @var SocialNetwork[], ArrayCollection
     * @ORM\OneToMany(targetEntity="SocialNetwork", mappedBy="user", orphanRemoval=true, cascade={"persist"})
     */
    private $socialNetworks;

    private function __construct(Id $id, DateTimeImmutable $registerDate, Name $name)
    {
        $this->id = $id;
        $this->registerDate = $registerDate;
        $this->name = $name;
        $this->role = Role::user();
        $this->socialNetworks = new ArrayCollection();
    }

    public static function signUpByEmail(
        Id $id,
        DateTimeImmutable $registerDate,
        Name $name,
        Email $email,
        string $passwordHash,
        string $confirmToken
    ): self
    {
        $user = new self($id, $registerDate, $name);

        $user->email = $email;
        $user->passwordHash = $passwordHash;
        $user->confirmToken = $confirmToken;
        $user->status = self::STATUS_WAIT;

        return $user;
    }

    public static function signUpBySocialNetwork(
        Id $id,
        DateTimeImmutable $registerDate,
        Name $name,
        string $socialNetwork,
        string $identity
    ): self
    {
        $user = new self($id, $registerDate, $name);

        $user->attachNetwork($socialNetwork, $identity);
        $user->status = self::STATUS_ACTIVE;

        return $user;
    }

    public static function create(Id $id, DateTimeImmutable $date, Name $name, Email $email, string $passwordHash): self
    {
        $user = new self($id, $date, $name);
        $user->email = $email;
        $user->passwordHash = $passwordHash;
        $user->status = self::STATUS_ACTIVE;

        return $user;
    }

    public function activate(): void
    {
        if ($this->isActive()) {
            throw new DomainException('User is already active.');
        }

        $this->status = self::STATUS_ACTIVE;
    }

    public function block(): void
    {
        if ($this->isBlocked()) {
            throw new DomainException('User is already blocked.');
        }

        $this->status = self::STATUS_BLOCKED;
    }

    public function attachNetwork(string $network, string $identity): void
    {
        /** @var SocialNetwork $n */
        foreach ($this->socialNetworks as $n) {
            if ($n->hasName($network)) {
                throw new DomainException('Social network is already attached.');
            }
        }

        $this->socialNetworks->add(new SocialNetwork($this, $network, $identity));
    }

    public function detachNetwork(string $network, string $identity): void
    {
        if (!$this->email && $this->socialNetworks->count() === 1) {
            throw new DomainException('Unable to detach the last social network.');
        }

        /** @var SocialNetwork $n */
        foreach ($this->socialNetworks as $n) {
            if ($n->equals($network, $identity)) {
                $this->socialNetworks->removeElement($n);
                return;
            }
        }

        throw new DomainException('Social network is not attached.');
    }

    public function isWait(): bool
    {
        return $this->status === self::STATUS_WAIT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    public function getId(): Id
    {
        return $this->id;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getRegisterDate(): DateTimeImmutable
    {
        return $this->registerDate;
    }

    public function getConfirmToken(): ?string
    {
        return $this->confirmToken;
    }

    public function confirmSignUp(): void
    {
        if ($this->isActive()) {
            throw new DomainException('User is already confirmed');
        }

        $this->status = self::STATUS_ACTIVE;
        $this->confirmToken = null;
    }

    public function requestPasswordReset(ResetPasswordToken $token, DateTimeImmutable $date): void
    {
        if (!$this->isActive()) {
            throw new DomainException('User is not active');
        }

        if (!$this->email) {
            throw new DomainException('Email is not specified');
        }

        if ($this->resetPasswordToken && !$this->resetPasswordToken->isExpiredTo($date)) {
            throw new DomainException('Reset password is already requested');
        }

        $this->resetPasswordToken = $token;
    }

    public function getResetPasswordToken(): ResetPasswordToken
    {
        return $this->resetPasswordToken;
    }

    public function passwordReset(DateTimeImmutable $date, string $passwordHash): void
    {
        if (!$this->resetPasswordToken) {
            throw new DomainException('Resetting password has not been requested');
        }

        if ($this->resetPasswordToken->isExpiredTo($date)) {
            throw new DomainException('Reset password token is expired');
        }

        $this->passwordHash = $passwordHash;
    }

    public function requestEmailChanging(Email $email, string $token): void
    {
        if (!$this->isActive()) {
            throw new DomainException('User is not active.');
        }

        if ($this->email && $this->email->isEqual($email)) {
            throw new DomainException('Email is already same.');
        }

        $this->newEmail = $email;
        $this->newEmailToken = $token;
    }

    public function confirmEmailChanging(string $token): void
    {
        if (!$this->newEmailToken) {
            throw new DomainException('Changing is not requested.');
        }

        if ($this->newEmailToken !== $token) {
            throw new DomainException('Incorrect changing token.');
        }

        $this->email = $this->newEmail;
        $this->newEmail = null;
        $this->newEmailToken = null;
    }

    public function getNewEmail(): ?Email
    {
        return $this->newEmail;
    }

    public function getNewEmailToken(): ?string
    {
        return $this->newEmailToken;
    }

    public function getName(): Name
    {
        return $this->name;
    }

    public function edit(Email $email, Name $name): void
    {
        $this->name = $name;
        $this->email = $email;
    }

    public function changeName(Name $name): void
    {
        $this->name = $name;
    }

    public function changeRole(Role $role): void
    {
        if ($this->role->isEqualTo($role)) {
            throw new DomainException('Role is already set');
        }

        $this->role = $role;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return SocialNetwork[]
     */
    public function getSocialNetworks(): array
    {
        return $this->socialNetworks->toArray();
    }

    /**
     * @ORM\PostLoad()
     */
    public function checkEmbeds(): void
    {
        if ($this->resetPasswordToken->isEmpty()) {
            $this->resetPasswordToken = null;
        }
    }
}
