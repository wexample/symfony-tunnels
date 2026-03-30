<?php

namespace Wexample\SymfonyTunnels\Service\Step\Traits;

use App\Wex\BaseBundle\Service\FormProcessor\AbstractFormProcessor;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyTunnels\Service\FormProcessor\Traits\TunnelFormProcessorTrait;

trait FormTunnelStepTrait
{
    protected AbstractFormProcessor $formProcessor;
    protected ?FormInterface $form = null;

    public function init(): void
    {
        $this->getFormProcessor()->setTunnelStep($this);
    }

    /**
     * @return AbstractFormProcessor|TunnelFormProcessorTrait
     */
    protected function getFormProcessor(): AbstractFormProcessor
    {
        return $this->formProcessor;
    }

    protected function setFormProcessor(
        AbstractFormProcessor $formProcessor
    ): void {
        $this->formProcessor = $formProcessor;
    }

    /**
     * Overrides TunnelStep default method.
     */
    public function handleRequest(Request $request): self|Response|null
    {
        $formProcessor = $this->getFormProcessor();

        if ($request->isMethod(Request::METHOD_POST)) {
            $form = $formProcessor->handleSubmission($request);
        } else {
            $form = $formProcessor->createForm();
        }

        $this->form = $form;
        $this->onFormRender($form);

        $response = $formProcessor->handleSubmissionResponseFromForm($form);
        if ($response instanceof Response) {
            return $response;
        }

        return $this;
    }

    public function getViewParams(): array
    {
        $params = parent::getViewParams();

        if ($this->form) {
            $params['form'] = $this->form->createView();
        }

        return $params;
    }

    public function onFormRender(FormInterface $form): void
    {
        // To override..
    }

    public function onFormValid(FormInterface $form): void
    {
        // To override..
    }
}
