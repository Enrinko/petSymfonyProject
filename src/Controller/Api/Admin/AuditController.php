<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Application\Audit\ListAuditEventsHandler;
use App\Application\Audit\ListAuditEventsQuery;
use App\Domain\Audit\AuditAction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/audit')]
#[IsGranted('ROLE_ADMIN')]
final class AuditController extends AbstractController
{
    #[Route('', name: 'api_admin_audit_list', methods: ['GET'])]
    public function list(Request $request, ListAuditEventsHandler $handler): JsonResponse
    {
        $result = $handler(new ListAuditEventsQuery(
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('perPage', 30),
            action: $request->query->get('action'),
            actorEmail: $request->query->get('actor'),
            from: $request->query->get('from'),
            to: $request->query->get('to'),
        ));

        return $this->json($result + ['actions' => AuditAction::values()]);
    }
}
