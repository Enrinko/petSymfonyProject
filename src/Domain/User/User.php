<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\User\Exception\UnknownRoleException;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    /** null = email ещё не подтверждён. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deactivatedAt = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarPath = null;

    private function __construct(string $email, string $hashedPassword)
    {
        $this->email = $email;
        $this->password = $hashedPassword;
        $this->roles = [Role::User->value];
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function register(string $email, string $hashedPassword): self
    {
        return new self($email, $hashedPassword);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Тот же пользователь: по идентичности объекта или по ненулевому id.
     * Надёжно и для managed-сущностей Doctrine, и для не сохранённых (id === null).
     */
    public function isSameAs(self $other): bool
    {
        return $this === $other || ($this->id !== null && $this->id === $other->id);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        \assert($this->email !== '');

        return $this->email;
    }

    /**
     * @return non-empty-list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = Role::User->value;

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     *
     * @throws UnknownRoleException
     */
    public function changeRoles(array $roles): void
    {
        foreach ($roles as $role) {
            if (Role::tryFrom($role) === null) {
                throw new UnknownRoleException(sprintf('Unknown role "%s".', $role));
            }
        }

        $this->roles = array_values(array_unique([Role::User->value, ...$roles]));
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function changePassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Сессия жива, пока юзер «тот же»: смена пароля или деактивация
     * инвалидируют токен при следующем запросе (ContextListener refresh —
     * user checker там не вызывается, эту работу делает EquatableInterface).
     */
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $user->getUserIdentifier() === $this->getUserIdentifier()
            && $user->getPassword() === $this->getPassword()
            && $user->isActive() === $this->isActive();
    }

    public function isVerified(): bool
    {
        return $this->verifiedAt !== null;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    /** Идемпотентно: повторный клик по ссылке не сдвигает дату подтверждения. */
    public function markVerified(): void
    {
        $this->verifiedAt ??= new \DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getDeactivatedAt(): ?\DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->deactivatedAt = new \DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->active = true;
        $this->deactivatedAt = null;
    }

    public function isAdmin(): bool
    {
        return \in_array(Role::Admin->value, $this->getRoles(), true);
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    /** Пустая строка означает «убрать имя» — хранится null. */
    public function rename(?string $displayName): void
    {
        $displayName = $displayName === null ? null : trim($displayName);

        $this->displayName = $displayName === '' ? null : $displayName;
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function changeAvatar(?string $avatarPath): void
    {
        $this->avatarPath = $avatarPath;
    }

    /** Имя для интерфейса: displayName, иначе email. */
    public function getDisplayLabel(): string
    {
        return $this->displayName ?? $this->email;
    }

    /** Инициалы для заглушки аватара. */
    public function getInitials(): string
    {
        $source = $this->getDisplayLabel();

        $words = preg_split('/[\s@._-]+/u', $source, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = implode('', array_map(
            static fn (string $word): string => mb_substr($word, 0, 1),
            \array_slice($words, 0, 2),
        ));

        return mb_strtoupper($initials !== '' ? $initials : mb_substr($source, 0, 2));
    }
}
