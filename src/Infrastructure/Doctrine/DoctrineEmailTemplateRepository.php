<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Domain\Email\EmailTemplate;
use App\Domain\Email\EmailTemplateRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineEmailTemplateRepository implements EmailTemplateRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function find(string $templateKey, string $locale): ?EmailTemplate
    {
        return $this->entityManager->getRepository(EmailTemplate::class)
            ->findOneBy(['templateKey' => $templateKey, 'locale' => $locale]);
    }

    public function findAll(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(EmailTemplate::class, 't')
            ->orderBy('t.templateKey', 'ASC')
            ->addOrderBy('t.locale', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(EmailTemplate $template): void
    {
        $this->entityManager->persist($template);
        $this->entityManager->flush();
    }
}
