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
    public function handleRequest(Request $request): Response
    {
        return $this
            ->getFormProcessor()
            ->handleStaticFormOrRenderAdaptiveResponse(
                $this->getView(),
                $this->getViewParams()
            );
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
