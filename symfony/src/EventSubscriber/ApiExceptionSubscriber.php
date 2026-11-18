<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Validation\ValidationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Renders exceptions under /api as JSON, matching the Laravel responses.
 */
class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 16],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof ValidationException) {
            $event->setResponse(new JsonResponse([
                'message' => $exception->getMessage(),
                'errors' => $exception->getErrors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY));

            return;
        }

        if ($exception instanceof AuthenticationException) {
            $event->setResponse(new JsonResponse(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED));

            return;
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            $event->setResponse(new JsonResponse(['message' => 'Method not allowed.'], Response::HTTP_METHOD_NOT_ALLOWED));

            return;
        }

        if ($exception instanceof NotFoundHttpException) {
            $event->setResponse(new JsonResponse([
                'message' => $exception->getMessage() ?: 'Resource not found.',
            ], Response::HTTP_NOT_FOUND));

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $event->setResponse(new JsonResponse(
                ['message' => $exception->getMessage() ?: (Response::$statusTexts[$exception->getStatusCode()] ?? 'Error.')],
                $exception->getStatusCode(),
                $exception->getHeaders(),
            ));
        }
    }
}
