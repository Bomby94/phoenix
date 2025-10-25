<?php

namespace App\Controller\Admin;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('admin/')]
class MonitoringController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('monitoring')]
    public function monitoring(): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $this->logger->error('first sentry error');

        return $this->json(['status' => 'ok']);
    }
}
