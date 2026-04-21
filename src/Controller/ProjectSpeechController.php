<?php

namespace App\Controller;

use App\Entity\Decision;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\DecisionRepository;
use App\Repository\ProjectRepository;
use App\Service\GoogleCloudTextToSpeechService;
use App\Service\ProjectSpeechMessageBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProjectSpeechController extends AbstractController
{
    #[Route('/projects/{id}/speech/{scenario}', name: 'project_speech', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function projectSpeech(
        int $id,
        string $scenario,
        ProjectRepository $projectRepository,
        DecisionRepository $decisionRepository,
        ProjectSpeechMessageBuilder $messageBuilder,
        GoogleCloudTextToSpeechService $textToSpeechService
    ): Response {
        $user = $this->getCurrentUser();
        $project = $projectRepository->findOneVisibleWithDecisions($id, $user, $this->canSeeAllProjects($user));

        if (!$project instanceof Project) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas ecouter ce projet.');
        }

        $message = match ($scenario) {
            'summary' => $messageBuilder->buildProjectSummary($project),
            'submission-confirmation' => $messageBuilder->buildSubmissionConfirmation($project),
            'decision-announcement' => $this->buildDecisionAnnouncementMessage($project, $decisionRepository, $messageBuilder),
            'refusal-reason' => $this->buildRefusalReasonMessage($project, $decisionRepository, $messageBuilder),
            default => throw $this->createNotFoundException('Scenario vocal introuvable.'),
        };

        try {
            $audioContent = $textToSpeechService->synthesize($message);
        } catch (\RuntimeException $exception) {
            return new Response($exception->getMessage(), Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new Response($audioContent, Response::HTTP_OK, [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    #[Route('/projects/{id}/speech-feed', name: 'project_speech_feed', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function projectSpeechFeed(
        int $id,
        Request $request,
        ProjectRepository $projectRepository,
        DecisionRepository $decisionRepository
    ): JsonResponse {
        $user = $this->getCurrentUser();
        $project = $projectRepository->findOneVisibleWithDecisions($id, $user, $this->canSeeAllProjects($user));

        if (!$project instanceof Project) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter ce flux vocal.');
        }

        $afterDecisionId = max(0, (int) $request->query->get('afterDecisionId', 0));
        $latestDecision = $decisionRepository->findLatestAnnounceableForProject($project);

        if (!$latestDecision instanceof Decision) {
            return $this->json([
                'latestDecisionId' => 0,
                'hasNewAnnouncement' => false,
                'scenario' => null,
                'audioUrl' => null,
                'status' => null,
            ]);
        }

        $latestDecisionId = $latestDecision->getId() ?? 0;
        $hasNewAnnouncement = $latestDecisionId > $afterDecisionId;

        return $this->json([
            'latestDecisionId' => $latestDecisionId,
            'hasNewAnnouncement' => $hasNewAnnouncement,
            'scenario' => $hasNewAnnouncement ? 'decision-announcement' : null,
            'audioUrl' => $hasNewAnnouncement ? $this->generateUrl('project_speech', [
                'id' => $project->getId(),
                'scenario' => 'decision-announcement',
            ]) : null,
            'status' => $latestDecision->getDecisionTitle(),
        ]);
    }

    #[Route('/back/projects/speech-feed', name: 'back_project_speech_feed', methods: ['GET'])]
    public function backProjectSpeechFeed(
        Request $request,
        ProjectRepository $projectRepository
    ): JsonResponse {
        $user = $this->getCurrentUser();
        if (!$this->canSeeAllProjects($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter ce flux vocal back-office.');
        }

        $afterProjectId = max(0, (int) $request->query->get('afterProjectId', 0));
        $projects = $projectRepository->findClientProjectsCreatedAfterId($afterProjectId);
        $newProjects = [];
        $maxProjectId = $afterProjectId;

        foreach ($projects as $project) {
            if (!$project instanceof Project || $project->getId() === null) {
                continue;
            }

            $maxProjectId = max($maxProjectId, $project->getId());
            $newProjects[] = [
                'id' => $project->getId(),
                'title' => $project->getTitle(),
                'audioUrl' => $this->generateUrl('back_project_new_speech', ['id' => $project->getId()]),
            ];
        }

        return $this->json([
            'newProjects' => $newProjects,
            'maxProjectId' => $maxProjectId,
        ]);
    }

    #[Route('/back/projects/{id}/speech/new-project-announcement', name: 'back_project_new_speech', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function backProjectNewSpeech(
        int $id,
        ProjectRepository $projectRepository,
        ProjectSpeechMessageBuilder $messageBuilder,
        GoogleCloudTextToSpeechService $textToSpeechService
    ): Response {
        $user = $this->getCurrentUser();
        if (!$this->canSeeAllProjects($user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas ecouter cette annonce back-office.');
        }

        $project = $projectRepository->findOneVisibleWithDecisions($id, $user, true);
        if (!$project instanceof Project) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        try {
            $audioContent = $textToSpeechService->synthesize($messageBuilder->buildNewProjectAlert($project));
        } catch (\RuntimeException $exception) {
            return new Response($exception->getMessage(), Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new Response($audioContent, Response::HTTP_OK, [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function buildDecisionAnnouncementMessage(
        Project $project,
        DecisionRepository $decisionRepository,
        ProjectSpeechMessageBuilder $messageBuilder
    ): string {
        $decision = $decisionRepository->findLatestAnnounceableForProject($project);
        if (!$decision instanceof Decision) {
            throw $this->createNotFoundException('Aucune decision vocale disponible.');
        }

        return $messageBuilder->buildDecisionAnnouncement($project, $decision);
    }

    private function buildRefusalReasonMessage(
        Project $project,
        DecisionRepository $decisionRepository,
        ProjectSpeechMessageBuilder $messageBuilder
    ): string {
        $decision = $decisionRepository->findLatestAnnounceableForProject($project);
        if (!$decision instanceof Decision || $decision->getDecisionTitle() !== Decision::STATUS_REFUSED) {
            throw $this->createNotFoundException('Aucune justification de refus disponible.');
        }

        return $messageBuilder->buildRefusalReason($project, $decision);
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function canSeeAllProjects(?User $user): bool
    {
        return $user instanceof User && in_array($user->getRoleUser(), ['admin', 'gerant'], true);
    }
}
