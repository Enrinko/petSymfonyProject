<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\User\Exception\UnknownRoleException;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
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
}
