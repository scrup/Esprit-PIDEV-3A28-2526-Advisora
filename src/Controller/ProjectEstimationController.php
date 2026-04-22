<?php

namespace App\Controller;

use App\Dto\ProjectEstimationRequest;
use App\Form\ProjectEstimationType;
use App\Service\ProjectEstimationAnalyzerInterface;
use App\Service\ProjectEstimationMetaAwareInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProjectEstimationController extends AbstractController
{
    #[Route('/projects/estimation', name: 'project_estimation', methods: ['GET', 'POST'])]
    public function index(Request $request, ProjectEstimationAnalyzerInterface $estimationAnalyzer): Response
    {
        $estimationRequest = new ProjectEstimationRequest();
        $form = $this->createForm(ProjectEstimationType::class, $estimationRequest, [
            'attr' => [
                'novalidate' => 'novalidate',
                'data-estimation-form' => 'true',
            ],
        ]);
        $form->handleRequest($request);

        $estimation = null;
        $error = null;
        $estimationMeta = [
            'provider_used' => null,
            'used_fallback' => false,
            'warning' => null,
            'model' => null,
        ];
        $submittedStep = max(1, min(5, (int) $request->request->get('wizard_step', 1)));
        $currentStep = $submittedStep;

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $estimation = $estimationAnalyzer->estimate($estimationRequest);
                    if ($estimationAnalyzer instanceof ProjectEstimationMetaAwareInterface) {
                        $estimationMeta = $estimationAnalyzer->getLastEstimationMeta();
                    }
                    $currentStep = 5;
                } catch (\Throwable $exception) {
                    $message = trim($exception->getMessage());
                    $error = $message !== ''
                        ? $message
                        : 'Impossible de generer l estimation tunisienne pour le moment. Merci de reessayer dans quelques instants.';
                }
            } else {
                $currentStep = $this->resolveCurrentStep($form, $submittedStep);
            }
        }

        return $this->render('front/project/estimation.html.twig', [
            'form' => $form->createView(),
            'estimation' => $estimation,
            'error' => $error,
            'estimation_meta' => $estimationMeta,
            'current_step' => $currentStep,
        ]);
    }

    private function resolveCurrentStep(FormInterface $form, int $submittedStep): int
    {
        $fieldStepMap = [
            'projectName' => 1,
            'projectType' => 1,
            'projectDescription' => 1,
            'launchRegion' => 1,
            'desiredLaunchDate' => 1,
            'totalBudgetDt' => 2,
            'marketingBudgetDt' => 2,
            'fundingSource' => 2,
            'estimatedMonthlyRevenueDt' => 2,
            'estimatedProfitabilityDelayMonths' => 2,
            'teamSize' => 3,
            'founderExperienceYears' => 3,
            'teamKeySkills' => 3,
            'alreadyLaunchedInTunisia' => 3,
            'targetMarket' => 4,
            'directCompetitorsTunisia' => 4,
            'competitiveAdvantage' => 4,
            'tunisianMarketStudyStatus' => 4,
            'exportTarget' => 4,
            'mvpStatus' => 5,
            'mainTechnology' => 5,
            'plannedLegalStatus' => 5,
            'needsCertification' => 5,
            'tunisianSpecificRisks' => 5,
        ];

        $currentStep = $submittedStep;
        foreach ($fieldStepMap as $fieldName => $step) {
            if (!$form->has($fieldName)) {
                continue;
            }

            $field = $form->get($fieldName);
            if ($field->getErrors(true)->count() > 0) {
                $currentStep = min($currentStep, $step);
            }
        }

        return max(1, min(5, $currentStep));
    }
}
